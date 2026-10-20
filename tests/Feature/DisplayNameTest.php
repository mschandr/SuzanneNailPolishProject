<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DisplayNameTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_without_a_display_name_is_sent_to_choose_one(): void
    {
        $user = User::factory()->create(['display_name' => null]);

        $this->actingAs($user)->get('/home')->assertRedirect('/display-name');
    }

    public function test_choosing_a_name_lets_the_user_through(): void
    {
        $user = User::factory()->create(['display_name' => null]);

        $this->actingAs($user)->post('/display-name', ['display_name' => 'Suzanne'])
            ->assertRedirect('/home');

        $this->assertSame('Suzanne', $user->fresh()->display_name);
        $this->actingAs($user->fresh())->get('/home')->assertOk();
    }

    public function test_a_blank_name_is_refused(): void
    {
        $user = User::factory()->create(['display_name' => null]);

        $this->actingAs($user)->post('/display-name', ['display_name' => '   '])
            ->assertSessionHasErrors('display_name');

        $this->assertNull($user->fresh()->display_name);
    }
}
