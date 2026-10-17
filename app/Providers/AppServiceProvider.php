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
        RateLimiter::for('magic-link', fn (Request $request) => [
            Limit::perMinute(5)->by('ip:'.$request->ip()),
            Limit::perHour(30)->by('ip-hour:'.$request->ip()),
            // Strip +tags so a+1@x.com and a+2@x.com share one limit.
            Limit::perHour(5)->by('email:'.preg_replace('/\+[^@]*@/', '@', strtolower((string) $request->input('email')))),
        ]);
    }
}
