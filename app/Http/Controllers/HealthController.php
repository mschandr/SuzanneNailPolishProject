<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

class HealthController extends Controller
{
    public function __invoke()
    {
        DB::select('select 1');
        Queue::size();

        return response()->json(['app' => 'ok', 'database' => 'ok', 'queue' => 'ok']);
    }
}
