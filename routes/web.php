<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Admin\AdminHomeController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\ImportController;
use App\Http\Controllers\Admin\InviteController;
use App\Http\Controllers\Admin\ModerationController;
use App\Http\Controllers\Admin\NeedsReviewController;
use App\Http\Controllers\Admin\PolishController as AdminPolishController;
use App\Http\Controllers\Admin\SuggestionController;
use App\Http\Controllers\Admin\ValueListController;
use App\Http\Controllers\Auth\DisplayNameController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\MagicLinkController;
use App\Http\Controllers\BottleController;
use App\Http\Controllers\BulkOwnController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\CollectionController;
use App\Http\Controllers\CreditsController;
use App\Http\Controllers\GalleryController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\HeartController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\InviteRequestController;
use App\Http\Controllers\LikedController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\ManicureController;
use App\Http\Controllers\ManicureLayerController;
use App\Http\Controllers\ManicurePhotoController;
use App\Http\Controllers\ManicureSettingsController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\PersonController;
use App\Http\Controllers\PolishController;
use App\Http\Controllers\PrivatePolishController;
use App\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');
Route::get('/health', HealthController::class);
Route::view('/privacy', 'legal.privacy');
Route::view('/terms', 'legal.terms');

// Public routes whose access is decided per Manicure (private ones answer 404).
Route::get('/photos/{id}/{variant}', [ManicurePhotoController::class, 'show'])->whereUuid('id');

Route::get('/catalog', [CatalogController::class, 'index']);
Route::get('/catalog/{slug}', [CatalogController::class, 'brand']);

Route::middleware('guest')->group(function () {
    Route::get('/login', [MagicLinkController::class, 'create'])->name('login');
    Route::post('/login', [MagicLinkController::class, 'store'])->middleware('throttle:magic-link');
    Route::view('/login/sent', 'auth.sent');
    Route::get('/invite-request', [InviteRequestController::class, 'create']);
    Route::post('/invite-request', [InviteRequestController::class, 'store'])->middleware('throttle:magic-link');
    Route::view('/invite-request/sent', 'auth.invite-request-sent');
    Route::get('/login/{token}', [MagicLinkController::class, 'show'])->middleware('throttle:token');
    Route::post('/login/{token}', [MagicLinkController::class, 'consume'])->middleware('throttle:token');
});

// Outside the guest group: someone already signed in may still open an invite.
Route::get('/invite/{token}', [InviteRequestController::class, 'showInvite'])->middleware('throttle:token');
Route::post('/invite/{token}', [InviteRequestController::class, 'redeem'])->middleware('throttle:token');

Route::middleware('auth')->group(function () {
    Route::view('/display-name', 'auth.display-name');
    Route::post('/display-name', [DisplayNameController::class, 'store']);
    Route::post('/logout', LogoutController::class);

    // Reachable even before a display name is chosen: anyone must be able to leave.
    Route::get('/account/delete', [AccountController::class, 'confirmDelete']);
    Route::post('/account/delete', [AccountController::class, 'destroy']);

    // Every other signed-in page must live in this group so the display-name
    // requirement cannot be bypassed.
    Route::middleware('display-name')->group(function () {
        Route::get('/home', HomeController::class);
        Route::get('/welcome', [OnboardingController::class, 'show']);
        Route::post('/welcome/complete', [OnboardingController::class, 'complete']);
        Route::view('/help', 'help');

        Route::get('/collection', [CollectionController::class, 'index']);

        Route::get('/my-polishes', [PrivatePolishController::class, 'index']);
        Route::get('/my-polishes/create', [PrivatePolishController::class, 'create']);
        Route::post('/my-polishes', [PrivatePolishController::class, 'store']);
        Route::get('/my-polishes/{id}/edit', [PrivatePolishController::class, 'edit'])->whereUuid('id');
        Route::put('/my-polishes/{id}', [PrivatePolishController::class, 'update'])->whereUuid('id');
        Route::delete('/my-polishes/{id}', [PrivatePolishController::class, 'destroy'])->whereUuid('id');

        Route::post('/my-polishes/{id}/suggest', [PrivatePolishController::class, 'suggest'])->whereUuid('id');
        Route::delete('/my-polishes/{id}/suggest', [PrivatePolishController::class, 'withdraw'])->whereUuid('id');

        Route::post('/manicures/{id}/heart', [HeartController::class, 'store'])->whereUuid('id')->middleware('throttle:heart');
        Route::delete('/manicures/{id}/heart', [HeartController::class, 'destroy'])->whereUuid('id')->middleware('throttle:heart');
        Route::post('/manicures/{id}/report', [ReportController::class, 'store'])->whereUuid('id')->middleware('throttle:report');
        Route::get('/liked', [LikedController::class, 'index']);

        Route::get('/manicures', [ManicureController::class, 'index']);
        Route::get('/manicures/create', [ManicureController::class, 'create']);
        Route::post('/manicures', [ManicureController::class, 'store']);
        Route::get('/manicures/{id}/edit', [ManicureController::class, 'edit'])->whereUuid('id');
        Route::put('/manicures/{id}', [ManicureController::class, 'update'])->whereUuid('id');
        Route::delete('/manicures/{id}', [ManicureController::class, 'destroy'])->whereUuid('id');
        Route::get('/manicures/{id}/polishes', [ManicureLayerController::class, 'index'])->whereUuid('id');
        Route::post('/manicures/{id}/polishes', [ManicureLayerController::class, 'store'])->whereUuid('id');
        Route::put('/manicures/{id}/polish-mode', [ManicureLayerController::class, 'setMode'])->whereUuid('id');
        Route::post('/manicures/{id}/polishes/copy-to-nails', [ManicureLayerController::class, 'copyToNails'])->whereUuid('id');
        Route::put('/manicure-polishes/{id}', [ManicureLayerController::class, 'update'])->whereUuid('id');
        Route::post('/manicure-polishes/{id}/move', [ManicureLayerController::class, 'move'])->whereUuid('id');
        Route::delete('/manicure-polishes/{id}', [ManicureLayerController::class, 'destroy'])->whereUuid('id');
        Route::post('/manicure-photos/{id}/rotate', [ManicurePhotoController::class, 'rotate'])->whereUuid('id');
        Route::delete('/manicure-photos/{id}', [ManicurePhotoController::class, 'destroy'])->whereUuid('id');

        Route::get('/settings/manicures', [ManicureSettingsController::class, 'edit']);
        Route::put('/settings/manicures', [ManicureSettingsController::class, 'update']);

        Route::get('/locations', [LocationController::class, 'index']);
        Route::post('/locations', [LocationController::class, 'store']);
        Route::put('/locations/{id}', [LocationController::class, 'update'])->whereUuid('id');
        Route::delete('/locations/{id}', [LocationController::class, 'destroy'])->whereUuid('id');

        Route::put('/bottles/{id}', [BottleController::class, 'update'])->whereUuid('id');
        Route::delete('/bottles/{id}', [BottleController::class, 'destroy'])->whereUuid('id');

        Route::post('/bulk/own', [BulkOwnController::class, 'store']);
        Route::post('/polishes/{polish}/bottles', [BottleController::class, 'store'])->whereUuid('polish');

        Route::middleware('admin')->prefix('admin')->group(function () {
            Route::get('/', AdminHomeController::class);
            Route::get('/polishes', [AdminPolishController::class, 'index']);
            Route::get('/polishes/create', [AdminPolishController::class, 'create']);
            Route::post('/polishes', [AdminPolishController::class, 'store']);
            Route::get('/polishes/{id}/edit', [AdminPolishController::class, 'edit'])->whereUuid('id');
            Route::put('/polishes/{id}', [AdminPolishController::class, 'update'])->whereUuid('id');
            Route::get('/imports', [ImportController::class, 'index']);
            Route::get('/imports/template.csv', [ImportController::class, 'template']);
            Route::post('/imports', [ImportController::class, 'store']);
            Route::get('/imports/{id}', [ImportController::class, 'show'])->whereUuid('id');
            Route::post('/imports/{id}/apply', [ImportController::class, 'apply'])->whereUuid('id');
            Route::get('/needs-review', [NeedsReviewController::class, 'index']);
            Route::get('/moderation', [ModerationController::class, 'index']);
            Route::get('/moderation/log', [ModerationController::class, 'log']);
            Route::post('/reports/{id}/dismiss', [ModerationController::class, 'dismiss'])->whereUuid('id');
            Route::post('/manicures/{id}/hide', [ModerationController::class, 'hide'])->whereUuid('id');
            Route::post('/manicures/{id}/restore', [ModerationController::class, 'restore'])->whereUuid('id');
            Route::delete('/manicures/{id}', [ModerationController::class, 'destroy'])->whereUuid('id');
            Route::post('/users/{id}/suspend', [ModerationController::class, 'suspend'])->whereUuid('id');
            Route::post('/users/{id}/unsuspend', [ModerationController::class, 'unsuspend'])->whereUuid('id');
            Route::get('/suggestions', [SuggestionController::class, 'index']);
            Route::post('/suggestions/{id}/promote', [SuggestionController::class, 'promote'])->whereUuid('id');
            Route::delete('/suggestions/{id}', [SuggestionController::class, 'decline'])->whereUuid('id');
            Route::get('/invites', [InviteController::class, 'index']);
            Route::post('/invites', [InviteController::class, 'store']);
            Route::get('/users', [AdminUserController::class, 'index']);
            Route::post('/users', [AdminUserController::class, 'store']);
            Route::delete('/users/{id}', [AdminUserController::class, 'destroy'])->whereUuid('id');

            foreach (['finishes', 'color-families'] as $list) {
                Route::get("/{$list}", [ValueListController::class, 'index'])->defaults('list', $list);
                Route::post("/{$list}", [ValueListController::class, 'store'])->defaults('list', $list);
                Route::put("/{$list}/{id}", [ValueListController::class, 'update'])->defaults('list', $list)->whereUuid('id');
            }
        });
    });
});

Route::get('/manicures/{id}', [ManicureController::class, 'show'])->whereUuid('id');
Route::get('/gallery', [GalleryController::class, 'index']);
Route::get('/credits', [CreditsController::class, 'index']);
Route::get('/people/{id}', [PersonController::class, 'show'])->whereUuid('id');

// Keep last: a catch-all for /<brand>/<uuid>/<color-name>, so it can never shadow another route.
Route::get('/{brand}/{id}/{slug}', [PolishController::class, 'show'])->whereUuid('id');
