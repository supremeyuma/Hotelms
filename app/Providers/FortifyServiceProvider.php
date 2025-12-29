<?php

namespace App\Providers;

use App\Models\User;
use App\Services\RoleRedirectService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(RoleRedirectService $redirectService): void
    {
        /**
         * --------------------------------------------
         * Authentication Logic
         * --------------------------------------------
         */
        Fortify::authenticateUsing(function (Request $request) {
            $request->validate([
                'email' => ['required', 'email'],
                'password' => ['required'],
            ]);

            $user = User::where('email', $request->email)->first();

            if ($user && Hash::check($request->password, $user->password)) {
                return $user;
            }

            return null;
        });

        /**
         * --------------------------------------------
         * Post-Login Redirect (ROLE-BASED)
         * --------------------------------------------
         */
        Fortify::loginResponse(function () use ($redirectService) {
            return redirect()->intended(
                $redirectService->redirectPath(auth()->user())
            );
        });

        /**
         * --------------------------------------------
         * Rate Limiting
         * --------------------------------------------
         */
        RateLimiter::for('login', function (Request $request) {
            $email = (string) $request->email;

            return Limit::perMinute(5)->by($email.$request->ip());
        });

        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });
    }
}
