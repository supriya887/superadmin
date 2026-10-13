<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Partner;

class EnsurePartnerSingleSession
{
    public function handle(Request $request, Closure $next)
    {
        // Sanctum already authenticated the request
        $partner = $request->user();

        // If this is not a partner, continue
        if (!$partner instanceof Partner) {
            return $next($request);
        }

        // Get the token used for this request
        $accessToken = $request->user()->currentAccessToken();

        // No token found
        if (!$accessToken) {
            return response()->json([
                'success' => false,
                'message' => 'Session expired.',
                'data' => null,
            ], 401);
        }

        // Partner's currently active token
        $activeToken = (string) ($partner->current_session_token ?? '');

        // If no active session is stored, allow the request
        if ($activeToken === '') {
            return $next($request);
        }

        // Token ID from Sanctum
        $tokenId = (string) $accessToken->id;

        // Your login stores it as api:123
        $expectedApiToken = 'api:' . $tokenId;

        // Allow both formats
        if (
            $activeToken !== $tokenId &&
            $activeToken !== $expectedApiToken
        ) {
            return response()->json([
                'success' => false,
                'message' => 'You have been logged out because your account was logged in from another device.',
                'data' => null,
            ], 401);
        }

        // Validate partner_id if supplied
        if ($request->has('partner_id')) {

            if ((int) $request->input('partner_id') !== (int) $partner->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized partner.',
                    'data' => null,
                ], 403);
            }
        }

        return $next($request);
    }
}