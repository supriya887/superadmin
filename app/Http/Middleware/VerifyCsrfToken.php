<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected $except = [
        'partner/register',
        'partner/login',
        'partner/register-api',
        'search-gstin',
        'superadmin/partner_invoices_webhook',
        'superadmin/payments/webhook',
    ];
}
