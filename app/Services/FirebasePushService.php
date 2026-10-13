<?php

namespace App\Services;

use App\Models\DeviceToken;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Firebase Cloud Messaging (HTTP v1) sender.
 *
 * Requires a Firebase service-account JSON path in FIREBASE_CREDENTIALS
 * (or config services.firebase.credentials) and FIREBASE_PROJECT_ID.
 */
class FirebasePushService
{
    private ?string $accessToken = null;
    private ?int $accessTokenExpiresAt = null;

    public function sendToPartner(
        int $partnerId,
        string $title,
        string $body,
        array $data = []
    ): int {
        if ($partnerId <= 0) {
            return 0;
        }

        $tokens = DeviceToken::query()
            ->where('partner_id', $partnerId)
            ->where('is_active', true)
            ->pluck('fcm_token')
            ->filter(fn ($t) => is_string($t) && trim($t) !== '')
            ->unique()
            ->values();

        if ($tokens->isEmpty()) {
            Log::info('FCM skipped: no device tokens', ['partner_id' => $partnerId]);

            return 0;
        }

        $sent = 0;
        foreach ($tokens as $token) {
            if ($this->sendToToken((string) $token, $title, $body, $data)) {
                $sent++;
            }
        }

        return $sent;
    }

    public function sendToToken(string $fcmToken, string $title, string $body, array $data = []): bool
    {
        $projectId = trim((string) config('services.firebase.project_id', ''));
        if ($projectId === '') {
            Log::warning('FCM skipped: FIREBASE_PROJECT_ID missing');

            return false;
        }

        $accessToken = $this->getAccessToken();
        if ($accessToken === null) {
            return false;
        }

        $stringData = [];
        foreach ($data as $key => $value) {
            $stringData[(string) $key] = is_scalar($value) || $value === null
                ? (string) $value
                : json_encode($value);
        }

        $clickLink = (string) ($data['link'] ?? config('services.firebase.web_click_link', ''));

        $payload = [
            'message' => [
                'token' => $fcmToken,
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                ],
                'data' => $stringData,
                'android' => [
                    'priority' => 'high',
                    'notification' => [
                        'channel_id' => (string) config('services.firebase.android_channel_id', 'high_importance'),
                        'sound' => 'default',
                    ],
                ],
                'apns' => [
                    'payload' => [
                        'aps' => [
                            'sound' => 'default',
                            'badge' => 1,
                        ],
                    ],
                ],
                // Laptop / browser (partner panel) — shows as OS toast via Chrome/Edge
                'webpush' => [
                    'notification' => [
                        'title' => $title,
                        'body' => $body,
                        'icon' => (string) config('services.firebase.web_icon', ''),
                    ],
                    'fcm_options' => array_filter([
                        'link' => $clickLink !== '' ? $clickLink : null,
                    ]),
                ],
            ],
        ];

        try {
            $response = Http::withToken($accessToken)
                ->acceptJson()
                ->timeout(20)
                ->post(
                    "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send",
                    $payload
                );

            if ($response->successful()) {
                DeviceToken::query()
                    ->where('fcm_token', $fcmToken)
                    ->update(['last_used_at' => now()]);

                return true;
            }

            $bodyJson = $response->json();
            $errorCode = (string) data_get($bodyJson, 'error.status', '');
            $errorMessage = (string) data_get($bodyJson, 'error.message', $response->body());

            // Drop dead tokens so they are not retried forever.
            if (
                $response->status() === 404
                || str_contains($errorMessage, 'Registered token')
                || str_contains($errorMessage, 'NOT_FOUND')
                || $errorCode === 'NOT_FOUND'
                || $errorCode === 'INVALID_ARGUMENT'
            ) {
                DeviceToken::query()
                    ->where('fcm_token', $fcmToken)
                    ->update(['is_active' => false]);
            }

            Log::warning('FCM send failed', [
                'status' => $response->status(),
                'error' => $errorMessage,
                'token_suffix' => substr($fcmToken, -12),
            ]);

            return false;
        } catch (\Throwable $e) {
            Log::warning('FCM send exception', [
                'error' => $e->getMessage(),
                'token_suffix' => substr($fcmToken, -12),
            ]);

            return false;
        }
    }

    private function getAccessToken(): ?string
    {
        if ($this->accessToken !== null && $this->accessTokenExpiresAt !== null && time() < ($this->accessTokenExpiresAt - 60)) {
            return $this->accessToken;
        }

        $credentialsPath = (string) config('services.firebase.credentials', '');
        if ($credentialsPath === '' || !is_file($credentialsPath)) {
            Log::warning('FCM skipped: service account file missing', [
                'path' => $credentialsPath,
            ]);

            return null;
        }

        $json = json_decode((string) file_get_contents($credentialsPath), true);
        if (!is_array($json) || empty($json['client_email']) || empty($json['private_key'])) {
            Log::warning('FCM skipped: invalid service account JSON');

            return null;
        }

        $now = time();
        $jwtHeader = $this->base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $jwtClaim = $this->base64UrlEncode(json_encode([
            'iss' => $json['client_email'],
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
        ]));

        $unsigned = $jwtHeader . '.' . $jwtClaim;
        $privateKey = openssl_pkey_get_private($json['private_key']);
        if ($privateKey === false) {
            Log::warning('FCM skipped: cannot parse private key');

            return null;
        }

        $signature = '';
        $ok = openssl_sign($unsigned, $signature, $privateKey, OPENSSL_ALGO_SHA256);
        if (!$ok) {
            Log::warning('FCM skipped: openssl_sign failed');

            return null;
        }

        $assertion = $unsigned . '.' . $this->base64UrlEncode($signature);

        try {
            $response = Http::asForm()
                ->timeout(20)
                ->post('https://oauth2.googleapis.com/token', [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $assertion,
                ]);

            if (!$response->successful()) {
                Log::warning('FCM OAuth token failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return null;
            }

            $this->accessToken = (string) $response->json('access_token');
            $expiresIn = (int) ($response->json('expires_in') ?? 3600);
            $this->accessTokenExpiresAt = time() + max(60, $expiresIn);

            return $this->accessToken !== '' ? $this->accessToken : null;
        } catch (\Throwable $e) {
            Log::warning('FCM OAuth exception', ['error' => $e->getMessage()]);

            return null;
        }
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
