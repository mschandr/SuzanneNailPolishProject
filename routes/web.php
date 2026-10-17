<?php

use App\Http\Controllers\Auth\DisplayNameController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\MagicLinkController;
use App\Http\Controllers\HealthController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');
Route::get('/health', HealthController::class);

Route::middleware('guest')->group(function () {
    Route::view('/login', 'auth.login')->name('login');
    Route::post('/login', [MagicLinkController::class, 'store'])->middleware('throttle:magic-link');
    Route::view('/login/sent', 'auth.sent');
    Route::get('/login/{token}', [MagicLinkController::class, 'show'])->middleware('throttle:20,1');
    Route::post('/login/{token}', [MagicLinkController::class, 'consume'])->middleware('throttle:20,1');
});

Route::middleware('auth')->group(function () {
    Route::view('/display-name', 'auth.display-name');
    Route::post('/display-name', [DisplayNameController::class, 'store']);
    Route::post('/logout', LogoutController::class);

    // Every other signed-in page must live in this group so the display-name
    // requirement cannot be bypassed.
    Route::middleware('display-name')->group(function () {
        Route::view('/home', 'home');
    });
});
