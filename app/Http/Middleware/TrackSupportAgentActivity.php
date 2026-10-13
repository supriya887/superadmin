<?php

namespace App\Http\Middleware;

use App\Services\SupportAgentPresenceService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Refreshes admin_user_creation.last_active_at for the logged-in Super Admin panel user
 * (throttled inside the service), which drives the "Active Now" indicator.
 */
class TrackSupportAgentActivity
{
    public function __construct(private SupportAgentPresenceService $presence) {}

    public function handle(Request $request, Closure $next): Response
    {
        $userId = (int) $request->session()->get('userId', 0);
        if ($userId > 0) {
            $this->presence->touch($userId);
        }

        return $next($request);
    }
}
