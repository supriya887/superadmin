<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SandboxAadhaarService
{
    protected $apiKey;
    protected $apiSecret;
    protected $baseUrl;
    protected $mode;

    public function __construct()
    {
        $this->mode = env('SANDBOX_MODE', 'test');
        
        if ($this->mode === 'live') {
            $this->apiKey = env('SANDBOX_API_KEY_LIVE');
            $this->apiSecret = env('SANDBOX_API_SECRET_LIVE');
            $this->baseUrl = env('SANDBOX_BASE_URL_LIVE', 'https://api.sandbox.co.in');
            Log::info('SandboxAadhaarService initialized in LIVE mode');
        } else {
            $this->apiKey = env('SANDBOX_API_KEY_TEST');
            $this->apiSecret = env('SANDBOX_API_SECRET_TEST');
            $this->baseUrl = env('SANDBOX_BASE_URL_TEST', 'https://test-api.sandbox.co.in');
            Log::info('SandboxAadhaarService initialized in TEST mode');
        }
    }

    /**
     * Get JWT Token from Sandbox API
     */
    public function getJwtToken($forceRefresh = false)
    {
        $cacheKey = 'sandbox_jwt_token_' . $this->mode;
        
        if (!$forceRefresh && Cache::has($cacheKey)) {
            $token = Cache::get($cacheKey);
            Log::info('Using cached JWT token', ['mode' => $this->mode]);
            return $token;
        }

        try {
            Log::info('Fetching new JWT token', [
                'mode' => $this->mode,
                'api_key' => substr($this->apiKey, 0, 10) . '...'
            ]);
            
            $authUrl = rtrim((string) $this->baseUrl, '/') . '/authenticate';
            $response = Http::withHeaders([
                'accept' => 'application/json',
                'x-api-key' => $this->apiKey,
                'x-api-secret' => $this->apiSecret,
                'x-api-version' => '1.0.0',
            ])->post($authUrl);

            if ($response->successful()) {
                $body = $response->json();
                $token = (string) (data_get($body, 'access_token') ?? data_get($body, 'data.access_token') ?? '');
                if ($token === '') {
                    Log::error('JWT token missing in authenticate response', [
                        'mode' => $this->mode,
                        'body' => $body,
                    ]);
                    return null;
                }
                Cache::put($cacheKey, $token, now()->addHours(23));
                Log::info('JWT token fetched successfully', ['mode' => $this->mode]);
                return $token;
            }
            
            Log::error('JWT token generation failed', [
                'mode' => $this->mode,
                'status' => $response->status(),
                'response' => $response->json()
            ]);
            
            return null;
        } catch (\Exception $e) {
            Log::error('JWT token error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Generate OTP for Aadhaar - LIVE API Call
     */
    public function generateOtp($aadhaarNumber, $orderId)
    {
        try {
            Log::info('Generating OTP', [
                'mode' => $this->mode,
                'aadhaar' => $this->maskAadhaar($aadhaarNumber),
                'order_id' => $orderId
            ]);

            $jwtToken = $this->getJwtToken();
            if (!$jwtToken) {
                return [
                    'success' => false,
                    'message' => 'Authentication failed. Please try again.'
                ];
            }

            $response = Http::withHeaders([
                'Authorization' => $jwtToken,
                'x-api-key' => $this->apiKey,
                'x-api-version' => '1.0.0',
                'Content-Type' => 'application/json'
            ])->post($this->baseUrl . '/kyc/aadhaar/okyc/otp', [
                '@entity' => 'in.co.sandbox.kyc.aadhaar.okyc.otp.request',
                'aadhaar_number' => $aadhaarNumber,
                'consent' => 'Y',
                'reason' => 'KYC verification for B2B order placement'
            ]);

            Log::info('OTP API Response', [
                'status' => $response->status(),
                'success' => $response->successful(),
                'body' => $response->json()
            ]);

            if ($response->successful()) {
                $responseData = $response->json();
                $referenceId = (string) (data_get($responseData, 'data.reference_id') ?? data_get($responseData, 'reference_id') ?? '');
                $providerMsg = (string) (data_get($responseData, 'data.message') ?? data_get($responseData, 'message') ?? '');

                if ($referenceId === '') {
                    Log::warning('OTP API success but reference_id missing', [
                        'mode' => $this->mode,
                        'order_id' => $orderId,
                        'message' => $providerMsg,
                    ]);

                    // Same Aadhaar OTP already generated — reuse active session if we still have it
                    $existingRef = Cache::get("aadhaar_ref_{$orderId}");
                    if (!$existingRef) {
                        try {
                            $existingRef = \App\Models\OrderPoDetail::query()
                                ->where('order_id', (int) $orderId)
                                ->value('aadhaar_ref_no');
                        } catch (\Throwable) {
                            $existingRef = null;
                        }
                    }
                    if (!$existingRef) {
                        try {
                            $existingRef = \App\Models\PartnerAadhaarVerification::query()
                                ->where('order_id', (int) $orderId)
                                ->orderByDesc('id')
                                ->value('aadhaar_ref_no');
                        } catch (\Throwable) {
                            $existingRef = null;
                        }
                    }
                    $existingRef = trim((string) $existingRef);

                    if ($existingRef !== '' && (stripos($providerMsg, 'try after') !== false || stripos($providerMsg, 'already') !== false)) {
                        Cache::put("aadhaar_ref_{$orderId}", $existingRef, now()->addMinutes(30));
                        return [
                            'success' => true,
                            'message' => $providerMsg !== '' ? $providerMsg : 'OTP sent successfully',
                            'reference_id' => $existingRef,
                            'mode' => $this->mode,
                        ];
                    }

                    // No usable session — stop here, do not treat as success
                    return [
                        'success' => false,
                        'message' => $providerMsg !== '' ? $providerMsg : 'Failed to send OTP',
                    ];
                }

                Cache::put("aadhaar_ref_{$orderId}", $referenceId, now()->addMinutes(30));

                return [
                    'success' => true,
                    'message' => $providerMsg !== '' ? $providerMsg : 'OTP sent successfully',
                    'reference_id' => $referenceId,
                    'mode' => $this->mode,
                ];
            }

            // Handle token expiration and retry
            if ($response->status() === 401) {
                Log::warning('Token expired, refreshing...');
                $this->getJwtToken(true);
                return $this->generateOtp($aadhaarNumber, $orderId);
            }

            $error = $response->json();
            $errorMessage = $error['message'] ?? $error['data']['message'] ?? 'Failed to send OTP';
            
            return [
                'success' => false,
                'message' => $errorMessage
            ];

        } catch (\Exception $e) {
            Log::error('OTP generation error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage() !== '' ? $e->getMessage() : 'Failed to send OTP',
            ];
        }
    }

 public function verifyOtp($orderId, $otp, $attempt = 1, $fallbackRef = '')
{
    try {
        $referenceId = Cache::get("aadhaar_ref_{$orderId}");
        if (!$referenceId && $fallbackRef !== '') {
            $referenceId = (string) $fallbackRef;
            Cache::put("aadhaar_ref_{$orderId}", $referenceId, now()->addMinutes(30));
        }
        
        if (!$referenceId) {
            return [
                'success' => false,
                'message' => 'Session expired. Please send OTP again.'
            ];
        }

        Log::info('Verifying OTP', [
            'order_id' => $orderId,
            'reference_id' => $referenceId,
            'attempt' => $attempt,
            'mode' => $this->mode
        ]);

        $jwtToken = $this->getJwtToken();
        if (!$jwtToken) {
            return [
                'success' => false,
                'message' => 'Authentication failed. Please try again.'
            ];
        }

        // FIX: reference_id must be sent as STRING, not integer
        $requestBody = [
            '@entity' => 'in.co.sandbox.kyc.aadhaar.okyc.request',
            'reference_id' => (string)$referenceId,  // Changed to string
            'otp' => (string)$otp
        ];

        Log::info('Sending verify request', [
            'reference_id' => (string) $referenceId,
            'attempt' => $attempt,
        ]);

        $response = Http::timeout(25)->withHeaders([
            'Authorization' => $jwtToken,
            'x-api-key' => $this->apiKey,
            'x-api-version' => '1.0.0',
            'Content-Type' => 'application/json'
        ])->post($this->baseUrl . '/kyc/aadhaar/okyc/otp/verify', $requestBody);

        $responseBody = $response->json();
        Log::info('Verify API Response', [
            'status' => $response->status(),
            'attempt' => $attempt,
            'message' => $responseBody['message'] ?? data_get($responseBody, 'data.message'),
            'data_status' => data_get($responseBody, 'data.status'),
        ]);

        if ($response->successful()) {
            $responseData = $responseBody;
            
            if (isset($responseData['data'])) {
                $userData = $responseData['data'];
                
                if (($userData['status'] ?? '') === 'VALID') {
                    Cache::forget("aadhaar_ref_{$orderId}");
                    
                    return [
                        'success' => true,
                        'message' => 'Aadhaar verified successfully!',
                        'data' => [
                            'name' => $userData['name'] ?? 'N/A',
                            'date_of_birth' => $userData['date_of_birth'] ?? 'N/A',
                            'gender' => $userData['gender'] ?? 'N/A',
                            'address' => $userData['full_address'] ?? $userData['address'] ?? 'N/A',
                            'photo' => $userData['photo'] ?? null
                        ]
                    ];
                }

                $dataMsg = (string) ($userData['message'] ?? '');

                // UIDAI sometimes needs a short wait before result is ready
                if ($attempt < 3 && stripos($dataMsg, 'under process') !== false) {
                    sleep(2);
                    return $this->verifyOtp($orderId, $otp, $attempt + 1, $fallbackRef);
                }

                if (stripos($dataMsg, 'Invalid OTP') !== false) {
                    return [
                        'success' => false,
                        'message' => $dataMsg,
                    ];
                }
                if (stripos($dataMsg, 'Expired') !== false || stripos($dataMsg, 'OTP expired') !== false) {
                    // Old session is dead — force user to request a fresh OTP
                    Cache::forget("aadhaar_ref_{$orderId}");
                    return [
                        'success' => false,
                        'message' => $dataMsg,
                    ];
                }

                return [
                    'success' => false,
                    'message' => $dataMsg !== '' ? $dataMsg : 'OTP verification failed.'
                ];
            }
        }

        // Handle token expiration
        if ($response->status() === 401 && $attempt < 2) {
            Log::warning('Token expired, refreshing...');
            $this->getJwtToken(true);
            return $this->verifyOtp($orderId, $otp, $attempt + 1, $fallbackRef);
        }

        $error = is_array($responseBody) ? $responseBody : [];
        $errorMessage = (string) ($error['message'] ?? data_get($error, 'data.message') ?? 'OTP verification failed');

        // Do NOT auto-retry on Source Unavailable.
        // Retry right after 503 makes Sandbox return fake "OTP expired" for a still-valid OTP.
        $isSourceDown = $response->status() === 503
            || stripos($errorMessage, 'Source Unavailable') !== false;

        if ($isSourceDown) {
            Log::warning('Aadhaar source unavailable', [
                'order_id' => $orderId,
                'reference_id' => (string) $referenceId,
                'message' => $errorMessage,
            ]);
        } elseif (stripos($errorMessage, 'Expired') !== false) {
            Cache::forget("aadhaar_ref_{$orderId}");
        }

        return [
            'success' => false,
            'message' => $errorMessage,
        ];

    } catch (\Exception $e) {
        Log::error('OTP verification error: ' . $e->getMessage());

        return [
            'success' => false,
            'message' => $e->getMessage() !== '' ? $e->getMessage() : 'OTP verification failed.',
        ];
    }
}
    /**
     * Mask Aadhaar number
     */
    public function maskAadhaar($aadhaar)
    {
        if (strlen($aadhaar) !== 12) {
            return 'XXXX-XXXX-XXXX';
        }
        return substr($aadhaar, 0, 4) . ' XXXX ' . substr($aadhaar, -2);
    }

    /**
     * Get current mode
     */
    public function getMode()
    {
        return $this->mode;
    }
}