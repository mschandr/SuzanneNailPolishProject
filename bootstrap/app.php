<?php

use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureDisplayName;
use App\Http\Middleware\EnsureNotSuspended;
use App\Http\Middleware\NoCookiesOnPublicPhotos;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo('/login');
        $middleware->web(
            prepend: [NoCookiesOnPublicPhotos::class],
            append: [EnsureNotSuspended::class],
        );
        $middleware->alias([
            'display-name' => EnsureDisplayName::class,
            'admin' => EnsureAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
