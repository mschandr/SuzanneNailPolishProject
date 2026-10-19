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

        $status = ['app' => 'ok', 'database' => 'ok', 'queue' => 'ok'];

        // Make it obvious (to monitoring, to anyone looking) if the rate limits have been switched off.
        if (config('nailpolish.load_test_mode')) {
            $status['warning'] = 'LOAD_TEST_MODE is on: rate limits are disabled. Never leave this on in production.';
        }

        return response()->json($status);
    }
}
