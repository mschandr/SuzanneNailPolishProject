<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminGrantTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_can_grant_the_role_by_email(): void
    {
        $admin = User::factory()->admin()->create();
        $suzanne = User::factory()->create(['email' => 'suzanne@example.com']);

        $this->actingAs($admin)->post('/admin/users', ['email' => 'Suzanne@Example.com'])
            ->assertRedirect('/admin/users');

        $this->assertTrue($suzanne->fresh()->is_admin);
    }

    public function test_granting_to_an_unknown_email_is_refused(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('/admin/users', ['email' => 'nobody@example.com'])
            ->assertSessionHasErrors('email');
    }

    public function test_an_admin_can_revoke_another_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $other = User::factory()->admin()->create();

        $this->actingAs($admin)->delete("/admin/users/{$other->id}")->assertRedirect('/admin/users');

        $this->assertFalse($other->fresh()->is_admin);
    }

    public function test_an_admin_cannot_revoke_themselves(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->delete("/admin/users/{$admin->id}")->assertSessionHasErrors('user');

        $this->assertTrue($admin->fresh()->is_admin);
    }

    public function test_an_ordinary_user_cannot_grant(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($user)->post('/admin/users', ['email' => $other->email])->assertForbidden();

        $this->assertFalse($other->fresh()->is_admin);
    }
}
