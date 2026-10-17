<?php

use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\InviteController;
use App\Http\Controllers\Admin\ValueListController;
use App\Http\Controllers\Auth\DisplayNameController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\MagicLinkController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\InviteRequestController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');
Route::get('/health', HealthController::class);

Route::middleware('guest')->group(function () {
    Route::get('/login', [MagicLinkController::class, 'create'])->name('login');
    Route::post('/login', [MagicLinkController::class, 'store'])->middleware('throttle:magic-link');
    Route::view('/login/sent', 'auth.sent');
    Route::get('/invite-request', [InviteRequestController::class, 'create']);
    Route::post('/invite-request', [InviteRequestController::class, 'store'])->middleware('throttle:magic-link');
    Route::view('/invite-request/sent', 'auth.invite-request-sent');
    Route::get('/login/{token}', [MagicLinkController::class, 'show'])->middleware('throttle:20,1');
    Route::post('/login/{token}', [MagicLinkController::class, 'consume'])->middleware('throttle:20,1');
});

// Outside the guest group: someone already signed in may still open an invite.
Route::get('/invite/{token}', [InviteRequestController::class, 'showInvite'])->middleware('throttle:20,1');
Route::post('/invite/{token}', [InviteRequestController::class, 'redeem'])->middleware('throttle:20,1');

Route::middleware('auth')->group(function () {
    Route::view('/display-name', 'auth.display-name');
    Route::post('/display-name', [DisplayNameController::class, 'store']);
    Route::post('/logout', LogoutController::class);

    // Every other signed-in page must live in this group so the display-name
    // requirement cannot be bypassed.
    Route::middleware('display-name')->group(function () {
        Route::view('/home', 'home');

        Route::middleware('admin')->prefix('admin')->group(function () {
            Route::view('/', 'admin.index');
            Route::get('/invites', [InviteController::class, 'index']);
            Route::post('/invites', [InviteController::class, 'store']);
            Route::get('/users', [AdminUserController::class, 'index']);
            Route::post('/users', [AdminUserController::class, 'store']);
            Route::delete('/users/{user}', [AdminUserController::class, 'destroy'])->whereUuid('user');

            foreach (['finishes', 'color-families'] as $list) {
                Route::get("/{$list}", [ValueListController::class, 'index'])->defaults('list', $list);
                Route::post("/{$list}", [ValueListController::class, 'store'])->defaults('list', $list);
                Route::put("/{$list}/{id}", [ValueListController::class, 'update'])->defaults('list', $list)->whereUuid('id');
            }
        });
    });
});
