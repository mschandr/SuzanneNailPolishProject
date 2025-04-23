<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\PolishController;
use App\Http\Controllers\LocationsController;
use App\Http\Controllers\WornController;

Route::get('/', function () {
    return view('home');
});


Route::resource('brands', BrandController::class);
Route::resource('locations', LocationsController::class);
Route::resource('polishes', PolishController::class);
Route::get('worn/recent', [WornController::class, 'recent'])->name('worn.recent');
Route::get('worn/search', [WornController::class, 'search'])->name('worn.search');
Route::resource('worn', WornController::class);
