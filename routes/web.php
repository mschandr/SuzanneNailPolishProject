<?php

use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\InviteController;
use App\Http\Controllers\Admin\PolishController as AdminPolishController;
use App\Http\Controllers\Admin\SuggestionController;
use App\Http\Controllers\Admin\ValueListController;
use App\Http\Controllers\Auth\DisplayNameController;
use App\Http\Controllers\BottleController;
use App\Http\Controllers\BulkOwnController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\CollectionController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\MagicLinkController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\InviteRequestController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\PolishController;
use App\Http\Controllers\PrivatePolishController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');
Route::get('/health', HealthController::class);

Route::get('/catalog', [CatalogController::class, 'index']);
Route::get('/catalog/{slug}', [CatalogController::class, 'brand']);

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

        Route::get('/collection', [CollectionController::class, 'index']);

        Route::get('/my-polishes', [PrivatePolishController::class, 'index']);
        Route::get('/my-polishes/create', [PrivatePolishController::class, 'create']);
        Route::post('/my-polishes', [PrivatePolishController::class, 'store']);
        Route::get('/my-polishes/{id}/edit', [PrivatePolishController::class, 'edit'])->whereUuid('id');
        Route::put('/my-polishes/{id}', [PrivatePolishController::class, 'update'])->whereUuid('id');
        Route::delete('/my-polishes/{id}', [PrivatePolishController::class, 'destroy'])->whereUuid('id');

        Route::post('/my-polishes/{id}/suggest', [PrivatePolishController::class, 'suggest'])->whereUuid('id');
        Route::delete('/my-polishes/{id}/suggest', [PrivatePolishController::class, 'withdraw'])->whereUuid('id');

        Route::get('/locations', [LocationController::class, 'index']);
        Route::post('/locations', [LocationController::class, 'store']);
        Route::put('/locations/{id}', [LocationController::class, 'update'])->whereUuid('id');
        Route::delete('/locations/{id}', [LocationController::class, 'destroy'])->whereUuid('id');

        Route::put('/bottles/{id}', [BottleController::class, 'update'])->whereUuid('id');
        Route::delete('/bottles/{id}', [BottleController::class, 'destroy'])->whereUuid('id');

        Route::post('/bulk/own', [BulkOwnController::class, 'store']);
        Route::post('/polishes/{polish}/bottles', [BottleController::class, 'store'])->whereUuid('polish');

        Route::middleware('admin')->prefix('admin')->group(function () {
            Route::view('/', 'admin.index');
            Route::get('/polishes/create', [AdminPolishController::class, 'create']);
            Route::post('/polishes', [AdminPolishController::class, 'store']);
            Route::get('/polishes/{id}/edit', [AdminPolishController::class, 'edit'])->whereUuid('id');
            Route::put('/polishes/{id}', [AdminPolishController::class, 'update'])->whereUuid('id');
            Route::get('/suggestions', [SuggestionController::class, 'index']);
            Route::post('/suggestions/{id}/promote', [SuggestionController::class, 'promote'])->whereUuid('id');
            Route::delete('/suggestions/{id}', [SuggestionController::class, 'decline'])->whereUuid('id');
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

// Keep last: a catch-all for /<brand>/<uuid>/<color-name>, so it can never shadow another route.
Route::get('/{brand}/{id}/{slug}', [PolishController::class, 'show'])->whereUuid('id');
