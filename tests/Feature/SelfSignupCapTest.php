<?php

namespace Tests\Feature;

use App\Mail\MagicLinkMail;
use App\Mail\NoAccountMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SelfSignupCapTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['nailpolish.self_signup_limit' => 2]);
        Mail::fake();
    }

    private function requestLink(string $email): ?string
    {
        $this->post('/login', ['email' => $email]);

        $url = null;
        foreach (Mail::queued(MagicLinkMail::class) as $mail) {
            if ($mail->hasTo($email)) {
                $url = $mail->url;
            }
        }

        return $url;
    }

    private function selfSignUp(string $email): void
    {
        $this->post($this->requestLink($email));
        auth()->logout();
    }

    public function test_new_accounts_are_created_until_the_limit_is_reached(): void
    {
        $this->selfSignUp('a@example.com');
        $this->selfSignUp('b@example.com');

        $this->assertSame(2, User::where('signup_method', 'self')->count());
    }

    public function test_once_closed_a_new_address_gets_no_sign_in_link(): void
    {
        $this->selfSignUp('a@example.com');
        $this->selfSignUp('b@example.com');

        $this->assertNull($this->requestLink('c@example.com'));
        Mail::assertQueued(NoAccountMail::class, fn ($m) => $m->hasTo('c@example.com'));
    }

    public function test_the_response_is_identical_open_or_closed(): void
    {
        $open = $this->post('/login', ['email' => 'a@example.com'])->headers->get('Location');
        $this->selfSignUp('a@example.com');
        $this->selfSignUp('b@example.com');
        $closed = $this->post('/login', ['email' => 'c@example.com'])->headers->get('Location');

        $this->assertSame($open, $closed);
    }

    public function test_a_link_requested_before_closing_cannot_create_an_account_after_it(): void
    {
        $late = $this->requestLink('late@example.com');
        $this->selfSignUp('a@example.com');
        $this->selfSignUp('b@example.com');

        $this->post($late)->assertRedirect('/login')
            ->assertSessionHasErrors(['email' => 'New sign-ups have just closed. You can request an invite at /invite-request.']);

        $this->assertGuest();
        $this->assertNull(User::where('email', 'late@example.com')->first());
    }

    public function test_existing_users_can_always_sign_in(): void
    {
        $this->selfSignUp('a@example.com');
        $this->selfSignUp('b@example.com');

        $this->post($this->requestLink('a@example.com'))->assertRedirect();

        $this->assertAuthenticated();
    }

    public function test_invited_and_admin_accounts_do_not_count(): void
    {
        User::factory()->create(['signup_method' => 'invite']);
        User::factory()->admin()->create(['signup_method' => 'admin']);

        $this->selfSignUp('a@example.com');
        $this->selfSignUp('b@example.com');

        $this->assertSame(2, User::where('signup_method', 'self')->count());
    }

    public function test_the_admin_email_can_sign_up_after_the_cap_without_counting(): void
    {
        config(['nailpolish.admin_email' => 'boss@example.com']);
        $this->selfSignUp('a@example.com');
        $this->selfSignUp('b@example.com');

        $this->post($this->requestLink('boss@example.com'));

        $this->assertTrue(auth()->user()->is_admin);
        $this->assertSame('admin', auth()->user()->signup_method);
    }
}
