<?php

namespace Tests\Feature;

use App\Mail\MagicLinkMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AdminGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_can_open_the_admin_area(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/admin')->assertOk();
    }

    public function test_an_ordinary_user_gets_403(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin')->assertForbidden();
    }

    public function test_a_guest_is_sent_to_sign_in(): void
    {
        $this->get('/admin')->assertRedirect('/login');
    }

    public function test_the_configured_admin_email_becomes_admin_on_first_sign_in(): void
    {
        config(['nailpolish.admin_email' => 'mark@example.com']);
        Mail::fake();
        $this->post('/login', ['email' => 'Mark@Example.com']);
        $url = null;
        Mail::assertQueued(MagicLinkMail::class, function ($m) use (&$url) {
            $url = $m->url;

            return true;
        });

        $this->post($url);

        $this->assertTrue(auth()->user()->is_admin);
    }

    public function test_other_emails_do_not_become_admin(): void
    {
        config(['nailpolish.admin_email' => 'mark@example.com']);
        Mail::fake();
        $this->post('/login', ['email' => 'suzanne@example.com']);
        $url = null;
        Mail::assertQueued(MagicLinkMail::class, function ($m) use (&$url) {
            $url = $m->url;

            return true;
        });

        $this->post($url);

        $this->assertFalse(auth()->user()->is_admin);
    }

    private function signIn(string $email): void
    {
        Mail::fake();
        $this->post('/login', ['email' => $email]);
        $url = null;
        Mail::assertQueued(MagicLinkMail::class, function ($m) use (&$url) {
            $url = $m->url;

            return true;
        });
        $this->post($url);
    }

    public function test_a_revoked_admin_is_not_promoted_again_while_another_admin_exists(): void
    {
        config(['nailpolish.admin_email' => 'mark@example.com']);
        User::factory()->admin()->create();
        User::factory()->create(['email' => 'mark@example.com']);

        $this->signIn('mark@example.com');

        $this->assertFalse(auth()->user()->is_admin);
    }

    public function test_the_configured_email_is_promoted_when_no_admin_exists(): void
    {
        config(['nailpolish.admin_email' => ' Mark@Example.com ']);
        User::factory()->create(['email' => 'mark@example.com']);

        $this->signIn('mark@example.com');

        $this->assertTrue(auth()->user()->is_admin);
    }
}
