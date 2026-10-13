<?php

use App\Http\Middleware\AdminLogin;
use App\Http\Middleware\AdminLoggedIn;
use App\Http\Middleware\Authenticate;
use App\Http\Middleware\EncryptCookies;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\EnsureSuperAdminRole;
use App\Http\Middleware\ForceJsonApiResponse;
use App\Http\Middleware\PartnerAuth;
use App\Http\Middleware\PreventRequestsDuringMaintenance;
use App\Http\Middleware\RedirectIfAuthenticated;
use App\Http\Middleware\TrimStrings;
use App\Http\Middleware\TrustProxies;
use App\Http\Middleware\UserAuthMiddleware;
use App\Http\Middleware\VerifyCsrfToken;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))

    /*
    |--------------------------------------------------------------------------
    | Routing
    |--------------------------------------------------------------------------
    */

    ->withRouting(

        /*
        |--------------------------------------------------------------------------
        | Main Web Routes
        |--------------------------------------------------------------------------
        */

        web: __DIR__.'/../routes/web.php',

        /*
        |--------------------------------------------------------------------------
        | API Routes
        |--------------------------------------------------------------------------
        */

        api: __DIR__.'/../routes/api.php',

        /*
        |--------------------------------------------------------------------------
        | Console Routes
        |--------------------------------------------------------------------------
        */

        commands: __DIR__.'/../routes/console.php',

        /*
        |--------------------------------------------------------------------------
        | Health Check
        |--------------------------------------------------------------------------
        */

        health: '/up',

        /*
        |--------------------------------------------------------------------------
        | Additional Routes
        |--------------------------------------------------------------------------
        */

        then: function () {

            /*
            |--------------------------------------------------------------------------
            | CRM Web Routes
            |--------------------------------------------------------------------------
            |
            | routes/web_crm.php
            |
            | Example:
            | /crm/login
            | /crm/dashboard
            |
            */

            Route::middleware('web')
                ->group(
                    base_path('routes/web_crm.php')
                );


            /*
            |--------------------------------------------------------------------------
            | API Rate Limiter
            |--------------------------------------------------------------------------
            */

            RateLimiter::for('api', function (Request $request) {

                $perMinute = app()->environment('local')
                    ? 500
                    : 240;

                return Limit::perMinute($perMinute)
                    ->by(
                        optional($request->user())->id
                        ?: $request->ip()
                    );
            });


            /*
            |--------------------------------------------------------------------------
            | Payment Rate Limiter
            |--------------------------------------------------------------------------
            */

            RateLimiter::for('payment', function (Request $request) {

                $invoiceKey = collect(
                    (array) $request->input('invoice_id', [])
                )
                    ->map(fn ($id) => (int) $id)
                    ->filter()
                    ->sort()
                    ->implode(',');

                $key = ($request->ip() ?? 'unknown')
                    . '|'
                    . (
                        $invoiceKey !== ''
                            ? $invoiceKey
                            : 'general'
                    );

                return Limit::perMinute(300)->by($key);
            });


            /*
            |--------------------------------------------------------------------------
            | CRM API Rate Limiter
            |--------------------------------------------------------------------------
            */

            RateLimiter::for('crm_api', function (Request $request) {

                $perMinute = app()->environment('local')
                    ? 200
                    : 100;

                return Limit::perMinute($perMinute)
                    ->by(
                        optional($request->user())->id
                        ?: $request->ip()
                    );
            });


            /*
            |--------------------------------------------------------------------------
            | CRM API Routes
            |--------------------------------------------------------------------------
            |
            | routes/api_crm.php contains CRM API routes.
            |
            | Example route inside api_crm.php:
            |
            | /crm/auth/login
            |
            | Final URL:
            |
            | /api/crm/auth/login
            |
            */

            Route::middleware('api')
                ->prefix('api')
                ->group(
                    base_path('routes/api_crm.php')
                );
        },
    )


    /*
    |--------------------------------------------------------------------------
    | Middleware
    |--------------------------------------------------------------------------
    */

    ->withMiddleware(function (Middleware $middleware) {

        /*
        |--------------------------------------------------------------------------
        | Global Middleware
        |--------------------------------------------------------------------------
        */

        $middleware->use([

            TrustProxies::class,

            \Illuminate\Http\Middleware\HandleCors::class,

            PreventRequestsDuringMaintenance::class,

            \Illuminate\Foundation\Http\Middleware\ValidatePostSize::class,

            TrimStrings::class,

            \Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull::class,
        ]);


        /*
        |--------------------------------------------------------------------------
        | Web Middleware
        |--------------------------------------------------------------------------
        */

        $middleware->web(replace: [

            \Illuminate\Cookie\Middleware\EncryptCookies::class
                => EncryptCookies::class,

            \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class
                => VerifyCsrfToken::class,
        ]);


        /*
        |--------------------------------------------------------------------------
        | API Middleware
        |--------------------------------------------------------------------------
        */

        $middleware->api(prepend: [

            ForceJsonApiResponse::class,
        ]);


        $middleware->api(append: [

            'throttle:api',
        ]);


        /*
        |--------------------------------------------------------------------------
        | Middleware Aliases
        |--------------------------------------------------------------------------
        */

        $middleware->alias([

            'adminLogin' => AdminLogin::class,

            'adminLoggedIn' => AdminLoggedIn::class,

            'super_admin' => EnsureSuperAdminRole::class,

            'role' => EnsureRole::class,

            'userAuth' => UserAuthMiddleware::class,

            'partnerAuth' => PartnerAuth::class,

            'auth' => Authenticate::class,

            'guest' => RedirectIfAuthenticated::class,
        ]);
    })


    /*
    |--------------------------------------------------------------------------
    | Exceptions
    |--------------------------------------------------------------------------
    */

    ->withExceptions(function (Exceptions $exceptions) {
        //
    })


    /*
    |--------------------------------------------------------------------------
    | Create Application
    |--------------------------------------------------------------------------
    */

    ->create();
