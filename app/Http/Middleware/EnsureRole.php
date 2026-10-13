<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $role = null;

        if (auth()->check()) {
            $role = auth()->user()->role ?? null;
        } else {
            $role = session('userRole');
        }

        if (!in_array($role, $roles, true)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'error' => true,
                    'message' => 'Unauthorized role access.',
                    'data' => null,
                ], 401);
            }

            abort(403, 'Unauthorized role access.');
        }

        return $next($request);
    }
}

