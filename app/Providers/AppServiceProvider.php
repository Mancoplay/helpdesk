<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;
use Illuminate\Http\Request;
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
        Paginator::useBootstrapFive();

        RateLimiter::for('auth-login', function (Request $request) {
            $email = (string) $request->input('email', '');
            return [
                Limit::perMinute(10)->by($request->ip()),
                Limit::perMinute(10)->by(mb_strtolower(trim($email))),
            ];
        });

        RateLimiter::for('tickets-live', fn (Request $request) => Limit::perMinute(180)->by((string) ($request->user()?->id ?? $request->ip())));
        RateLimiter::for('tickets-message', fn (Request $request) => Limit::perMinute(30)->by((string) ($request->user()?->id ?? $request->ip())));
        RateLimiter::for('tickets-remote', fn (Request $request) => Limit::perMinute(30)->by((string) ($request->user()?->id ?? $request->ip())));
        RateLimiter::for('notifications-summary', fn (Request $request) => Limit::perMinute(60)->by((string) ($request->user()?->id ?? $request->ip())));
    }
}
