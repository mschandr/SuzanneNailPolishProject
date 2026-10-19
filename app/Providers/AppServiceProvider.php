<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

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
        // Every limiter goes through limits() so LOAD_TEST_MODE can lift them all in one place.
        RateLimiter::for('magic-link', fn (Request $request) => $this->limits([
            Limit::perMinute(5)->by('ip:'.$request->ip()),
            Limit::perHour(30)->by('ip-hour:'.$request->ip()),
            // Strip +tags so a+1@x.com and a+2@x.com share one limit.
            Limit::perHour(5)->by('email:'.preg_replace('/\+[^@]*@/', '@', strtolower((string) $request->input('email')))),
        ]));

        // One-time sign-in and invite links: per IP and per route.
        RateLimiter::for('token', fn (Request $request) => $this->limits([
            Limit::perMinute(20)->by($request->ip().'|'.$request->route()?->uri()),
        ]));

        // Hearts and un-hearts share one budget per signed-in user; reports have their own.
        RateLimiter::for('heart', fn (Request $request) => $this->limits([
            Limit::perMinute(60)->by('heart|'.($request->user()?->id ?? $request->ip())),
        ]));
        RateLimiter::for('report', fn (Request $request) => $this->limits([
            Limit::perMinute(10)->by(($request->user()?->id ?? $request->ip()).'|report'),
        ]));
    }

    /** @param  array<int, Limit>  $limits */
    private function limits(array $limits): array|Limit
    {
        return config('nailpolish.load_test_mode') ? Limit::none() : $limits;
    }
}
