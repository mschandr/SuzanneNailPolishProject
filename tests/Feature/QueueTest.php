<?php

namespace Tests\Feature;

use App\Jobs\Heartbeat;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class QueueTest extends TestCase
{
    public function test_a_dispatched_job_runs_through_the_queue(): void
    {
        Cache::forget('heartbeat');

        Heartbeat::dispatch();

        $this->assertNotNull(Cache::get('heartbeat'));
    }
}
