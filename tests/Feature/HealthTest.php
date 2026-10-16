<?php

namespace Tests\Feature;

use Tests\TestCase;

class HealthTest extends TestCase
{
    public function test_health_page_reports_app_database_and_queue_ok(): void
    {
        $this->getJson('/health')
            ->assertOk()
            ->assertExactJson(['app' => 'ok', 'database' => 'ok', 'queue' => 'ok']);
    }
}
