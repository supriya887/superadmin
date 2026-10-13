<?php

namespace App\Services;

use App\Models\Partner;
use App\Models\PartnerSession;
use App\Models\PartnerUser;
use App\Models\PersonalAccessToken;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Device & login sessions for partner users (staff), backed by pgsql_auth.partner_sessions.
 * Every write is best-effort: a missing table or DB error must never break login/logout.
 */
class PartnerUserSessionService
{
    private const DISPLAY_TIMEZONE = 'Asia/Kolkata';

    private static ?bool $tableReady = null;

    public function isReady(): bool
    {
        if (self::$tableReady === null) {
            try {
                self::$tableReady = Schema::connection('pgsql_auth')->hasTable('partner_sessions');
            } catch (\Throwable $e) {
                self::$tableReady = false;
            }
        }

        return self::$tableReady;
    }

    /**
     * Store a new device session right after a partner user's token is issued.
     */
    public function record(Partner $partner, ?PartnerUser $user, int $tokenId, Request $request): void
    {
        if (!$this->isReady()) {
            return;
        }

        try {
            $ip = $this->clientIp($request);

            $session = PartnerSession::create([
                'partner_id' => (int) $partner->id,
                'partner_user_id' => $user?->id,
                'token_id' => $tokenId,
                'device_id' => $this->cleanString($request->input('device_id'), 191),
                'device_name' => $this->cleanString($request->input('device_name'), 120),
                'device_type' => $this->normalizeDeviceType($request->input('device_type')),
                'platform' => $this->cleanString($request->input('platform'), 30),
                'location' => null,
                'ip_address' => $ip,
                'user_agent' => $request->userAgent(),
                'logged_in' => now(),
                'last_active_at' => now(),
            ]);

            // Geo lookup is an external HTTP call, so it runs after the login response is sent.
            if ($this->isPublicIp($ip)) {
                $sessionId = (int) $session->id;
                app()->terminating(function () use ($sessionId, $ip) {
                    $this->fillLocation($sessionId, $ip);
                });
            }
        } catch (\Throwable $e) {
            Log::warning('Partner user session record failed: ' . $e->getMessage(), [
                'partner_id' => $partner->id,
                'partner_user_id' => $user?->id,
            ]);
        }
    }

    /**
     * Login revokes every API token of the partner, so all open sessions of that partner end too.
     */
    public function closeOpenSessionsForPartner(int $partnerId): void
    {
        if (!$this->isReady()) {
            return;
        }

        try {
            PartnerSession::query()
                ->where('partner_id', $partnerId)
                ->whereNull('logout')
                ->update(['logout' => now()]);
        } catch (\Throwable $e) {
            Log::warning('Partner session close failed: ' . $e->getMessage(), ['partner_id' => $partnerId]);
        }
    }

    public function closeByToken(int $tokenId): void
    {
        if (!$this->isReady() || $tokenId <= 0) {
            return;
        }

        try {
            PartnerSession::query()
                ->where('token_id', $tokenId)
                ->whereNull('logout')
                ->update(['logout' => now(), 'last_active_at' => now()]);
        } catch (\Throwable $e) {
            Log::warning('Partner session close by token failed: ' . $e->getMessage(), ['token_id' => $tokenId]);
        }
    }

    /**
     * Latest device sessions of a partner user, shaped for the "Device & Login Information" card.
     */
    public function devicesFor(PartnerUser $user, ?int $currentTokenId = null, int $limit = 10): array
    {
        if (!$this->isReady()) {
            return [];
        }

        $sessions = PartnerSession::query()
            ->where('partner_id', (int) $user->partner_id)
            ->where('partner_user_id', (int) $user->id)
            ->orderByDesc('logged_in')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        $tokenIds = $sessions->pluck('token_id')->filter()->unique()->values()->all();
        $liveTokens = $tokenIds
            ? PersonalAccessToken::query()->whereIn('id', $tokenIds)->get(['id', 'last_used_at'])->keyBy('id')
            : collect();

        return $sessions->map(function (PartnerSession $session) use ($liveTokens, $currentTokenId) {
            $token = $session->token_id ? $liveTokens->get($session->token_id) : null;
            $isActive = $session->logout === null && $token !== null;

            $lastSeen = collect([
                $session->logout,
                $session->last_active_at,
                $token?->last_used_at ? Carbon::parse($token->last_used_at) : null,
                $session->logged_in,
            ])->filter()->max();

            $location = (string) ($session->location ?? '');
            $loginTime = $this->formatLoginTime($session->logged_in);

            return [
                'session_id' => (int) $session->id,
                'device_id' => (string) ($session->device_id ?? ''),
                'device_name' => (string) ($session->device_name ?? ''),
                'device_type' => (string) ($session->device_type ?? ''),
                'platform' => (string) ($session->platform ?? ''),
                'location' => $location,
                'ip_address' => (string) ($session->ip_address ?? ''),
                'login_time' => $loginTime,
                'subtitle' => $location !== '' ? $location . ' · ' . $loginTime : $loginTime,
                'logged_in_at' => $session->logged_in?->toIso8601String(),
                'last_active_at' => $lastSeen?->toIso8601String(),
                'logged_out_at' => $session->logout?->toIso8601String(),
                'is_active' => $isActive,
                'is_current' => $isActive && $currentTokenId !== null && (int) $session->token_id === $currentTokenId,
                'is_blocked' => $session->blocked_at !== null,
                'status_label' => $isActive ? 'Active' : $this->formatAgo($lastSeen),
            ];
        })->values()->all();
    }

    /**
     * Log out one session (sessionId) or every active session of the partner user.
     *
     * @return int number of sessions deactivated
     */
    public function deactivate(PartnerUser $user, ?int $sessionId = null): int
    {
        if (!$this->isReady()) {
            return 0;
        }

        $sessions = PartnerSession::query()
            ->where('partner_id', (int) $user->partner_id)
            ->where('partner_user_id', (int) $user->id)
            ->whereNull('logout')
            ->when($sessionId, fn ($q) => $q->whereKey($sessionId))
            ->get(['id', 'token_id']);

        if ($sessions->isEmpty()) {
            return 0;
        }

        $tokenIds = $sessions->pluck('token_id')->filter()->map(fn ($id) => (int) $id)->unique()->values()->all();

        if ($tokenIds) {
            PersonalAccessToken::query()->whereIn('id', $tokenIds)->delete();

            $partner = Partner::find((int) $user->partner_id);
            $activeRef = (string) ($partner->current_session_token ?? '');
            if ($partner && in_array($activeRef, array_map(fn ($id) => 'api:' . $id, $tokenIds), true)) {
                $partner->update(['current_session_token' => null]);
            }
        }

        PartnerSession::query()
            ->whereIn('id', $sessions->pluck('id')->all())
            ->update(['logout' => now()]);

        return $sessions->count();
    }

    /**
     * Permanently block a device for this partner user (marks every session row of that device_id).
     */
    public function blockDevice(PartnerUser $user, string $deviceId): int
    {
        return PartnerSession::query()
            ->where('partner_id', (int) $user->partner_id)
            ->where('partner_user_id', (int) $user->id)
            ->where('device_id', $deviceId)
            ->update(['blocked_at' => now()]);
    }

    /**
     * Login gate. A user who has any blocked device must send device_id, otherwise omitting it would bypass the block.
     * Fails open when the blocked_at column does not exist yet.
     */
    public function isDeviceBlocked(PartnerUser $user, ?string $deviceId): bool
    {
        if (!$this->isReady()) {
            return false;
        }

        try {
            $blocked = PartnerSession::query()
                ->where('partner_user_id', (int) $user->id)
                ->whereNotNull('blocked_at');
            $deviceId = trim((string) $deviceId);

            return $deviceId === '' ? $blocked->exists() : $blocked->where('device_id', $deviceId)->exists();
        } catch (\Throwable $e) {
            Log::warning('Partner blocked device check failed: ' . $e->getMessage(), ['partner_user_id' => $user->id]);

            return false;
        }
    }

    private function formatLoginTime(?Carbon $time): string
    {
        if (!$time) {
            return '';
        }

        $local = $time->copy()->timezone(self::DISPLAY_TIMEZONE);
        $today = now(self::DISPLAY_TIMEZONE);

        if ($local->isSameDay($today)) {
            $day = 'Today';
        } elseif ($local->isSameDay($today->copy()->subDay())) {
            $day = 'Yesterday';
        } else {
            $day = $local->format('d M Y');
        }

        return $day . ', ' . $local->format('h:iA');
    }

    private function formatAgo(?Carbon $time): string
    {
        if (!$time) {
            return '';
        }

        $seconds = max(0, now()->getTimestamp() - $time->getTimestamp());

        if ($seconds < 60) {
            return 'Just now';
        }
        if ($seconds < 3600) {
            return intdiv($seconds, 60) . ' min ago';
        }
        if ($seconds < 86400) {
            return intdiv($seconds, 3600) . ' hr ago';
        }
        if ($seconds < 86400 * 30) {
            $days = intdiv($seconds, 86400);
            return $days . ($days === 1 ? ' day ago' : ' days ago');
        }

        return $time->copy()->timezone(self::DISPLAY_TIMEZONE)->format('d M Y');
    }

    public function fillLocation(int $sessionId, string $ip): void
    {
        try {
            $location = $this->lookupLocation($ip);
            if ($location !== null) {
                PartnerSession::query()->whereKey($sessionId)->update(['location' => $location]);
            }
        } catch (\Throwable $e) {
            Log::info('Partner session location fill failed: ' . $e->getMessage(), ['session_id' => $sessionId]);
        }
    }

    /**
     * "City, Country" for a public IP, cached per IP. Returns null for private/local IPs or failed lookups.
     */
    public function lookupLocation(string $ip): ?string
    {
        if (!$this->isPublicIp($ip)) {
            return null;
        }

        $cacheKey = 'geoip:location:' . $ip;
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached === '' ? null : $cached;
        }

        $location = null;
        try {
            $url = str_replace('{ip}', rawurlencode($ip), (string) config('services.geoip.url'));
            $response = Http::timeout(max(1, (int) config('services.geoip.timeout', 3)))
                ->acceptJson()
                ->get($url);

            if ($response->ok()) {
                $location = $this->parseLocation((array) $response->json());
            }
        } catch (\Throwable $e) {
            Log::info('GeoIP lookup failed: ' . $e->getMessage(), ['ip' => $ip]);
        }

        // Failed lookups are cached briefly so a flaky provider isn't hit on every login.
        Cache::put($cacheKey, $location ?? '', $location !== null ? now()->addDays(7) : now()->addHour());

        return $location;
    }

    /**
     * Understands ipapi.co, ip-api.com and ipwho.is response shapes.
     */
    private function parseLocation(array $data): ?string
    {
        if (
            ($data['error'] ?? false) === true
            || ($data['success'] ?? true) === false
            || strtolower((string) ($data['status'] ?? 'success')) === 'fail'
        ) {
            return null;
        }

        $city = $this->cleanString($data['city'] ?? null, 60);
        $country = $this->cleanString($data['country_name'] ?? $data['country'] ?? null, 60);
        $parts = array_filter([$city, $country]);

        return $parts ? implode(', ', $parts) : null;
    }

    /**
     * Real client IP. TrustProxies has no trusted proxies configured, so proxy headers are read here.
     */
    private function clientIp(Request $request): string
    {
        $candidates = [
            $request->header('CF-Connecting-IP'),
            $request->header('X-Real-IP'),
        ];

        foreach (explode(',', (string) $request->header('X-Forwarded-For', '')) as $forwarded) {
            $candidates[] = $forwarded;
        }

        foreach ($candidates as $candidate) {
            $candidate = trim((string) $candidate);
            if ($candidate !== '' && $this->isPublicIp($candidate)) {
                return $candidate;
            }
        }

        return (string) $request->ip();
    }

    private function isPublicIp(?string $ip): bool
    {
        return $ip !== null
            && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }

    private function normalizeDeviceType($value): ?string
    {
        $value = strtolower(trim((string) $value));

        return in_array($value, ['mobile', 'tablet', 'desktop'], true) ? $value : null;
    }

    private function cleanString($value, int $max): ?string
    {
        $value = trim(strip_tags((string) $value));

        return $value === '' ? null : mb_substr($value, 0, $max);
    }
}
