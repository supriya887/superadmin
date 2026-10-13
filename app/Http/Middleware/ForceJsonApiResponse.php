<?php

namespace App\Http\Middleware;

use App\Models\PersonalAccessToken;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;

class ForceJsonApiResponse
{
    public function handle(Request $request, Closure $next)
    {
        $request->headers->set('Accept', 'application/json');

        $publicUserPaths = [
            'api/v1/register',
            'api/v1/login',
            'api/v1/verify-otp',
            'api/v1/resend-otp',
            'api/v1/search-gstin',
            'api/v1/register-validation',
            'api/v1/user-login',
            'api/v1/user-login-verify-otp',
        ];

        if (
            $request->bearerToken()
            && !str_contains($request->path(), 'api/v1/partner')
            && !str_contains($request->path(), 'api/crm')
            && !in_array($request->path(), $publicUserPaths, true)
        ) {
            $accessToken = PersonalAccessToken::findToken($request->bearerToken());
            if (!$accessToken || !$accessToken->tokenable instanceof User) {
                return response()->json([
                    'success' => false,
                    'message' => 'You have been logged out because your account was logged in from another device.',
                    'data' => null,
                ], 401);
            }

            $user = $accessToken->tokenable;
            $activeToken = (string) ($user->current_session_token ?? '');
            $expectedApiToken = 'api:' . (string) $accessToken->id;

            if ($activeToken !== '' && $activeToken !== $expectedApiToken) {
                return response()->json([
                    'success' => false,
                    'message' => 'You have been logged out because your account was logged in from another device.',
                    'data' => null,
                ], 401);
            }
        }

        return $next($request);
    }
}
