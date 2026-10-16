<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class UuidKeysTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_user_gets_a_uuid_id(): void
    {
        $user = User::factory()->create();

        $this->assertTrue(Str::isUuid($user->fresh()->id));
    }
}
