<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\RateLimiter;

class AppServiceProvider extends ServiceProvider
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
    public function boot(): void
    {
        ResetPassword::createUrlUsing(fn ($user, string $token) => sprintf(
            '%s/reset-password?token=%s&email=%s',
            rtrim((string) env('FRONTEND_URL', 'http://localhost:3000'), '/'),
            urlencode($token),
            urlencode($user->getEmailForPasswordReset()),
        ));

        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)->by(
            strtolower((string) ($request->input('email') ?? $request->input('identifiant'))).'|'.$request->ip()
        ));
        RateLimiter::for('registration', fn (Request $request) => Limit::perHour(5)->by($request->ip()));
        RateLimiter::for('password-reset', fn (Request $request) => Limit::perMinute(3)->by($request->ip()));
    }
}
