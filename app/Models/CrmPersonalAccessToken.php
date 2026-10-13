<?php

namespace App\Models;

use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

class CrmPersonalAccessToken extends SanctumPersonalAccessToken
{
    protected $connection = 'pgsql_crm';

    protected $table = 'personal_access_tokens';

    public static function findFromRequest(Request $request): ?self
    {
        $token = static::extractToken($request);

        if ($token === null) {
            return null;
        }

        return static::findPresentedToken($token);
    }

    public static function findPresentedToken(string $token): ?self
    {
        $token = trim($token);

        if ($token === '') {
            return null;
        }

        if (stripos($token, 'Bearer ') === 0) {
            $token = trim(substr($token, 7));
        }

        // Value copied from personal_access_tokens.token (already SHA-256).
        if (preg_match('/^[a-f0-9]{64}$/i', $token)) {
            return static::query()->where('token', strtolower($token))->first();
        }

        return static::findToken($token);
    }

    public function crmUser(): ?CrmUser
    {
        if ((string) $this->tokenable_id === '') {
            return null;
        }

        $type = ltrim((string) $this->tokenable_type, '\\');

        if ($type !== CrmUser::class && $type !== 'App\\Models\\CrmUser') {
            return null;
        }

        return CrmUser::query()->find($this->tokenable_id);
    }

    public static function extractToken(Request $request): ?string
    {
        $token = $request->bearerToken();

        if (is_string($token) && $token !== '') {
            return $token;
        }

        $header = $request->header('Authorization')
            ?: $request->server('HTTP_AUTHORIZATION')
            ?: $request->server('REDIRECT_HTTP_AUTHORIZATION');

        if (is_string($header) && preg_match('/Bearer\s+(\S+)/i', $header, $matches)) {
            return $matches[1];
        }

        $fallback = $request->header('X-Access-Token');

        if (is_string($fallback) && $fallback !== '') {
            return $fallback;
        }

        return null;
    }
}
