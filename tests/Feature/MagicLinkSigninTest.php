<?php

namespace Tests\Feature;

use App\Mail\MagicLinkMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MagicLinkSigninTest extends TestCase
{
    use RefreshDatabase;

    private function requestLink(string $email): string
    {
        Mail::fake();
        $this->post('/login', ['email' => $email]);

        $url = null;
        Mail::assertQueued(MagicLinkMail::class, function ($m) use (&$url) {
            $url = $m->url;

            return true;
        });

        return $url;
    }

    public function test_the_link_signs_the_user_in(): void
    {
        $url = $this->requestLink('suzanne@example.com');

        $this->post($url)->assertRedirect();

        $this->assertAuthenticated();
        $this->assertSame('suzanne@example.com', auth()->user()->email);
    }

    public function test_opening_the_link_does_not_use_it_up(): void
    {
        $url = $this->requestLink('suzanne@example.com');

        $this->get($url)->assertOk()->assertSee('Sign in');
        $this->assertGuest();

        $this->post($url)->assertRedirect();
        $this->assertAuthenticated();
    }

    public function test_an_existing_user_is_signed_in_not_duplicated(): void
    {
        $user = User::factory()->create(['email' => 'suzanne@example.com']);

        $this->post($this->requestLink('suzanne@example.com'));

        $this->assertSame($user->id, auth()->id());
        $this->assertSame(1, User::count());
    }

    public function test_a_link_cannot_be_used_twice(): void
    {
        $url = $this->requestLink('suzanne@example.com');
        $this->post($url);
        auth()->logout();

        $this->post($url)->assertRedirect('/login');

        $this->assertGuest();
    }

    public function test_an_expired_link_is_rejected(): void
    {
        $url = $this->requestLink('suzanne@example.com');

        $this->travel(16)->minutes();
        $this->post($url)->assertRedirect('/login');

        $this->assertGuest();
    }

    public function test_a_made_up_token_is_rejected(): void
    {
        $this->post('/login/not-a-real-token')->assertRedirect('/login');

        $this->assertGuest();
    }

    public function test_an_existing_user_signs_in_without_waiting_for_the_signup_lock(): void
    {
        $user = User::factory()->create(['email' => 'suzanne@example.com']);
        $url = $this->requestLink('suzanne@example.com');

        // Someone else is in the middle of creating an account, holding the lock that counts the first 100 signups.
        $held = \Illuminate\Support\Facades\Cache::lock('self-signup', 30);
        $this->assertTrue($held->get());

        $started = microtime(true);
        $this->post($url)->assertRedirect();
        $held->release();

        $this->assertAuthenticated();
        $this->assertSame($user->id, auth()->id());
        $this->assertLessThan(2.0, microtime(true) - $started, 'an existing user must not queue behind account creation');
    }
}
