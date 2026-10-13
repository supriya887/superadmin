<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class PartnerAuth
{
    public function handle(Request $request, Closure $next)
    {
        if (!Session::has('partnerId') && !Session::has('partner_id')) {
            return redirect('/partner/login')->with('error', 'Please login to access the partner panel.');
        }

        return $next($request);
    }
}
