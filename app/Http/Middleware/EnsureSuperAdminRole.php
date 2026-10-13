<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSuperAdminRole
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->session()->has('userId') && ! $request->session()->has('userName')) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'error' => true,
                    'message' => 'Please login to access this page.',
                    'data' => null,
                ], 401);
            }

            return redirect()->route('superadmin.login')
                ->with('error', 'Please login to access this page.');
        }

        return $next($request);
    }
}
