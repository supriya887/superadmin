<?php

namespace App\Providers;

use App\Models\Order;
use App\Models\PersonalAccessToken;
use App\Observers\OrderObserver;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use App\Models\CrmPersonalAccessToken;
use Laravel\Sanctum\Sanctum;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        require_once app_path('helper.php');
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // Partner API tokens live on pgsql_auth.personal_access_tokens.
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

        Order::observe(OrderObserver::class);

        // Laravel 8 may not have Str::password(). Some of your codebase calls it.
        // Provide a compatible fallback so approvals/login flows won't crash.
        if (!method_exists(Str::class, 'password') && method_exists(Str::class, 'macro')) {
            Str::macro('password', function (
                $length = 8,
                $includeUppercase = true,
                $includeLowercase = true,
                $includeNumbers = true,
                $includeSymbols = true
            ) {
                $length = (int) $length;
                if ($length <= 0) {
                    $length = 8;
                }

                $lower = 'abcdefghijklmnopqrstuvwxyz';
                $upper = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
                $numbers = '0123456789';
                $symbols = '!@#$%^&*()-_=+[]{};:,.?';

                $characters = '';
                if ($includeLowercase) $characters .= $lower;
                if ($includeUppercase) $characters .= $upper;
                if ($includeNumbers) $characters .= $numbers;
                if ($includeSymbols) $characters .= $symbols;

                if ($characters === '') {
                    // Absolute fallback: random alpha string.
                    return Str::random($length);
                }

                $max = strlen($characters) - 1;
                $result = '';
                for ($i = 0; $i < $length; $i++) {
                    $result .= $characters[random_int(0, $max)];
                }

                return $result;
            });
        }
        Auth::viaRequest('crm-token', function (Request $request) {
            $accessToken = CrmPersonalAccessToken::findFromRequest($request);
            $user = $accessToken?->crmUser();

            if (!$accessToken || !$user) {
                return null;
            }

            if (method_exists($user, 'withAccessToken')) {
                $user->withAccessToken($accessToken);
            }

            $accessToken->forceFill(['last_used_at' => now()])->save();

            return $user;
        });
    }
}
