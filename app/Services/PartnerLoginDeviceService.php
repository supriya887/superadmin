<?php

namespace App\Services;

use App\Models\Partner;
use App\Models\PartnerLoginDevice;
use App\Models\PartnerUser;
use App\Models\PersonalAccessToken;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class PartnerLoginDeviceService
{
    public const MAX_ACTIVE = 1;

    public function isReady(): bool
    {
        try {
            return Schema::connection('pgsql_auth')->hasTable('partner_login_devices');
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function resolvePartnerUser(Partner $partner, Request $request): ?PartnerUser
    {
        $email = strtolower(trim((string) $request->input('email', '')));
        $mobile = preg_replace('/\D+/', '', (string) $request->input('mobile', '')) ?? '';
        $hintId = (int) $request->input('partner_user_id', 0);

        $query = PartnerUser::query()->where('partner_id', (int) $partner->id);

        if ($hintId > 0) {
            $user = (clone $query)->whereKey($hintId)->first();
            if ($user) {
                return $user;
            }
        }

        if ($email !== '') {
            $user = (clone $query)->whereRaw('LOWER(email) = ?', [$email])->first();
            if ($user) {
                return $user;
            }
        }

        if ($mobile !== '') {
            $user = (clone $query)->get()->first(function (PartnerUser $row) use ($mobile) {
                $rowMobile = preg_replace('/\D+/', '', (string) ($row->mobile ?? '')) ?? '';

                return $rowMobile !== '' && $rowMobile === $mobile;
            });
            if ($user) {
                return $user;
            }
        }

        return null;
    }

    /**
     * @return array{ok: bool, code?: string, message?: string, active_device?: array<string, mixed>, device?: PartnerLoginDevice}
     */
    public function registerLogin(
        Partner $partner,
        Request $request,
        ?int $tokenId,
        bool $logoutPrevious,
        ?PartnerUser $partnerUser = null
    ): array {
        if (!$this->isReady()) {
            return ['ok' => true];
        }

        $meta = $this->deviceMetaFromRequest($request);
        $owner = $this->ownerQuery((int) $partner->id, $partnerUser ? (int) $partnerUser->id : null);
        $active = (clone $owner)
            ->where('status', PartnerLoginDevice::STATUS_ACTIVE)
            ->orderByDesc('id')
            ->first();

        $sameDevice = $active
            && $meta['device_fingerprint'] !== ''
            && (string) $active->device_fingerprint === $meta['device_fingerprint'];

        if ($active && !$sameDevice && !$logoutPrevious) {
            return [
                'ok' => false,
                'code' => 'DEVICE_LIMIT',
                'message' => 'Only 1 device is allowed. Logout from '
                    . ((string) ($active->device_name ?: 'the last device'))
                    . ' to continue on this device.',
                'active_device' => $this->serializeDevice($active),
            ];
        }

        if ($active && !$sameDevice && $logoutPrevious) {
            $this->logoutDevice($active, 'Replaced by a new device login');
        }

        if ($sameDevice && $active) {
            if ((int) ($active->token_id ?? 0) > 0) {
                PersonalAccessToken::query()->whereKey((int) $active->token_id)->delete();
            }
            $active->fill([
                'token_id' => $tokenId,
                'device_name' => $meta['device_name'],
                'device_type' => $meta['device_type'],
                'platform' => $meta['platform'],
                'location' => $meta['location'] ?: $active->location,
                'ip_address' => $meta['ip_address'],
                'user_agent' => $meta['user_agent'],
                'status' => PartnerLoginDevice::STATUS_ACTIVE,
                'last_seen_at' => now(),
                'logged_out_at' => null,
            ]);
            $active->save();
            if ($partnerUser) {
                $partnerUser->last_login_at = now();
                $partnerUser->save();
            }

            return ['ok' => true, 'device' => $active];
        }

        $device = PartnerLoginDevice::query()->create([
            'partner_id' => (int) $partner->id,
            'partner_user_id' => $partnerUser ? (int) $partnerUser->id : null,
            'token_id' => $tokenId,
            'device_name' => $meta['device_name'],
            'device_type' => $meta['device_type'],
            'platform' => $meta['platform'],
            'device_fingerprint' => $meta['device_fingerprint'],
            'location' => $meta['location'],
            'ip_address' => $meta['ip_address'],
            'user_agent' => $meta['user_agent'],
            'status' => PartnerLoginDevice::STATUS_ACTIVE,
            'logged_in_at' => now(),
            'last_seen_at' => now(),
        ]);

        if ($partnerUser) {
            $partnerUser->last_login_at = now();
            $partnerUser->save();
        }

        return ['ok' => true, 'device' => $device];
    }

    public function logoutCurrent(?Partner $partner, ?int $tokenId): void
    {
        if (!$this->isReady() || !$partner) {
            return;
        }

        $query = PartnerLoginDevice::query()
            ->where('partner_id', (int) $partner->id)
            ->where('status', PartnerLoginDevice::STATUS_ACTIVE);

        if ($tokenId && $tokenId > 0) {
            $query->where('token_id', $tokenId);
        }

        $device = $query->orderByDesc('id')->first();
        if ($device) {
            $this->logoutDevice($device, 'Logged out');
        }
    }

    public function logoutDevice(PartnerLoginDevice $device, string $reason = 'Logged out'): void
    {
        if ((int) ($device->token_id ?? 0) > 0) {
            PersonalAccessToken::query()->whereKey((int) $device->token_id)->delete();
        }

        $device->status = PartnerLoginDevice::STATUS_LOGGED_OUT;
        $device->logged_out_at = now();
        $device->save();
        unset($reason);
    }

    public function logoutAllForUser(int $partnerId, int $partnerUserId): int
    {
        if (!$this->isReady() || $partnerUserId <= 0) {
            return 0;
        }

        $rows = PartnerLoginDevice::query()
            ->where('partner_id', $partnerId)
            ->where('partner_user_id', $partnerUserId)
            ->where('status', PartnerLoginDevice::STATUS_ACTIVE)
            ->get();

        foreach ($rows as $row) {
            $this->logoutDevice($row, 'User deactivated');
        }

        return $rows->count();
    }

    /**
     * Payload for User Detail → Device & Login Information.
     *
     * @return array<string, mixed>
     */
    public function payloadForUser(int $partnerId, int $partnerUserId): array
    {
        if (!$this->isReady()) {
            return $this->emptyPayload();
        }

        $rows = PartnerLoginDevice::query()
            ->where('partner_id', $partnerId)
            ->where('partner_user_id', $partnerUserId)
            ->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")
            ->orderByDesc('last_seen_at')
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        $devices = $rows->map(fn (PartnerLoginDevice $row) => $this->serializeDevice($row))->values()->all();
        $active = collect($devices)->first(fn ($row) => ($row['status'] ?? '') === PartnerLoginDevice::STATUS_ACTIVE);
        $history = collect($devices)->filter(fn ($row) => ($row['status'] ?? '') !== PartnerLoginDevice::STATUS_ACTIVE)->values()->all();
        $activeCount = $active ? 1 : 0;

        return [
            'max_active_devices' => self::MAX_ACTIVE,
            'active_count' => $activeCount,
            'can_add_device' => $activeCount < self::MAX_ACTIVE,
            'add_device_message' => $activeCount >= self::MAX_ACTIVE
                ? 'Only 1 device is allowed. Logout from the last device to add another.'
                : null,
            'active_device' => $active,
            'devices' => $devices,
            'device_history' => $history,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeDevice(PartnerLoginDevice $device): array
    {
        $isActive = strtolower((string) $device->status) === PartnerLoginDevice::STATUS_ACTIVE;
        $when = $device->last_seen_at ?? $device->logged_in_at ?? $device->logged_out_at;

        return [
            'id' => (int) $device->id,
            'device_name' => (string) ($device->device_name ?: 'Unknown device'),
            'device_type' => (string) ($device->device_type ?: 'desktop'),
            'platform' => $device->platform,
            'location' => (string) ($device->location ?: '-'),
            'ip_address' => $device->ip_address,
            'status' => $isActive ? 'active' : 'logged_out',
            'status_label' => $isActive ? 'Active' : $this->relativeLabel($device->logged_out_at ?? $when),
            'logged_in_at' => $when ? Carbon::parse($when)->format('d M Y, h:i A') : null,
            'logged_in_label' => $this->todayTimeLabel($when),
            'last_seen_at' => $device->last_seen_at ? $device->last_seen_at->toIso8601String() : null,
            'logged_out_at' => $device->logged_out_at ? $device->logged_out_at->toIso8601String() : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function emptyPayload(): array
    {
        return [
            'max_active_devices' => self::MAX_ACTIVE,
            'active_count' => 0,
            'can_add_device' => true,
            'add_device_message' => null,
            'active_device' => null,
            'devices' => [],
            'device_history' => [],
        ];
    }

    /**
     * @return array{device_name: string, device_type: string, platform: ?string, device_fingerprint: string, location: string, ip_address: ?string, user_agent: ?string}
     */
    public function deviceMetaFromRequest(Request $request): array
    {
        $ua = (string) $request->userAgent();
        $parsed = $this->parseUserAgent($ua);
        $deviceName = trim((string) $request->input('device_name', ''));
        if ($deviceName === '') {
            $deviceName = $parsed['device_name'];
        }

        $deviceType = strtolower(trim((string) $request->input('device_type', $parsed['device_type'])));
        if (!in_array($deviceType, ['mobile', 'desktop'], true)) {
            $deviceType = $parsed['device_type'];
        }

        $platform = strtolower(trim((string) $request->input('platform', $parsed['platform'] ?? '')));
        if (!in_array($platform, ['ios', 'android', 'web'], true)) {
            $platform = $parsed['platform'];
        }

        $fingerprint = trim((string) $request->input('device_id', $request->input('device_fingerprint', '')));
        if ($fingerprint === '') {
            $fingerprint = hash('sha256', implode('|', [
                strtolower($deviceName),
                $deviceType,
                $platform,
                $ua,
            ]));
        }

        $location = trim((string) $request->input('location', ''));
        if ($location === '') {
            $city = trim((string) ($request->header('CF-IPCity') ?? ''));
            $country = trim((string) ($request->header('CF-IPCountry') ?? ''));
            $location = trim($city . ($city !== '' && $country !== '' ? ', ' : '') . ($country !== '' ? strtoupper($country) : ''));
        }

        return [
            'device_name' => Str::limit($deviceName !== '' ? $deviceName : 'Unknown device', 160, ''),
            'device_type' => $deviceType,
            'platform' => $platform ?: null,
            'device_fingerprint' => substr($fingerprint, 0, 64),
            'location' => Str::limit($location, 160, ''),
            'ip_address' => $request->ip(),
            'user_agent' => $ua !== '' ? $ua : null,
        ];
    }

    /**
     * @return array{device_name: string, device_type: string, platform: ?string}
     */
    private function parseUserAgent(string $ua): array
    {
        $uaLower = strtolower($ua);
        $isMobile = (bool) preg_match('/mobile|iphone|ipad|android|ipod|blackberry|windows phone/i', $ua);

        $platform = 'web';
        if (str_contains($uaLower, 'iphone') || str_contains($uaLower, 'ipad') || str_contains($uaLower, 'ios')) {
            $platform = 'ios';
        } elseif (str_contains($uaLower, 'android')) {
            $platform = 'android';
        }

        $name = 'Web Browser';
        if (preg_match('/iPhone(?:\s*(?:OS\s*)?[\d_]+)?/i', $ua)) {
            $name = 'iPhone';
        } elseif (preg_match('/iPad/i', $ua)) {
            $name = 'iPad';
        } elseif (preg_match('/Android.*?;\s*([^;)]+)\s*Build/i', $ua, $m)) {
            $name = trim($m[1]);
        } elseif (preg_match('/Macintosh/i', $ua)) {
            $name = 'Mac';
        } elseif (preg_match('/Windows NT/i', $ua)) {
            $name = 'Windows PC';
        } elseif (preg_match('/Linux/i', $ua)) {
            $name = 'Linux PC';
        }

        if (preg_match('/Edg\//i', $ua)) {
            $name .= $name !== 'Web Browser' ? ' · Edge' : 'Edge';
        } elseif (preg_match('/Chrome\//i', $ua) && !preg_match('/Edg\//i', $ua)) {
            $name .= $name !== 'Web Browser' ? ' · Chrome' : 'Chrome';
        } elseif (preg_match('/Safari\//i', $ua) && !preg_match('/Chrome\//i', $ua)) {
            $name .= $name !== 'Web Browser' ? ' · Safari' : 'Safari';
        } elseif (preg_match('/Firefox\//i', $ua)) {
            $name .= $name !== 'Web Browser' ? ' · Firefox' : 'Firefox';
        }

        return [
            'device_name' => $name,
            'device_type' => $isMobile ? 'mobile' : 'desktop',
            'platform' => $platform,
        ];
    }

    private function todayTimeLabel($when): string
    {
        if (!$when) {
            return '-';
        }

        $dt = Carbon::parse($when);
        $today = Carbon::now()->startOfDay();
        if ($dt->copy()->startOfDay()->equalTo($today)) {
            return 'Today, ' . $dt->format('h:i A');
        }
        if ($dt->copy()->startOfDay()->equalTo($today->copy()->subDay())) {
            return 'Yesterday, ' . $dt->format('h:i A');
        }

        return $dt->format('d M Y, h:i A');
    }

    private function relativeLabel($when): string
    {
        if (!$when) {
            return 'Logged out';
        }

        return Carbon::parse($when)->diffForHumans();
    }

    private function ownerQuery(int $partnerId, ?int $partnerUserId)
    {
        $query = PartnerLoginDevice::query()->where('partner_id', $partnerId);
        if ($partnerUserId && $partnerUserId > 0) {
            $query->where('partner_user_id', $partnerUserId);
        } else {
            $query->whereNull('partner_user_id');
        }

        return $query;
    }
}
