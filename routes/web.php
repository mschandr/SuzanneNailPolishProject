<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/health', function () {
    DB::select('select 1');
    Queue::size();

    return response()->json(['app' => 'ok', 'database' => 'ok', 'queue' => 'ok']);
});
