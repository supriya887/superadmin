<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class UserAuthMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (!Auth::check() && !$request->session()->has('partnerId')) {
            return redirect()->route('user_login')->with('error', 'Please login to access this page.');
        }

        $userId = (int) (Auth::id() ?? $request->session()->get('partnerId', 0));
        $sessionToken = (string) $request->session()->get('user_session_token', '');
        $activeToken = $userId > 0
            ? (string) (DB::table('users')->where('id', $userId)->value('current_session_token') ?? '')
            : '';

        if ($userId > 0 && $activeToken !== '' && $sessionToken !== $activeToken) {
            $request->session()->forget([
                'partnerId',
                'partnerName',
                'partnerEmail',
                'token',
                'user_session_token',
            ]);
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            $message = 'You have been logged out because your account was logged in from another device.';

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $message,
                    'data' => null,
                ], 401);
            }

            return redirect()->route('user_login')->with('error', $message);
        }

        return $next($request);
    }
}
