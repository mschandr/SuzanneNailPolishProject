<?php

namespace Tests\Feature;

use App\Mail\InviteMail;
use App\Models\InviteRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class InviteCodeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    private function sendInvite(string $email): ?string
    {
        $this->actingAs(User::factory()->admin()->create())->post('/admin/invites', ['email' => $email]);
        auth()->logout();

        $url = null;
        foreach (Mail::queued(InviteMail::class) as $mail) {
            if ($mail->hasTo(strtolower($email))) {
                $url = $mail->url;
            }
        }

        return $url;
    }

    public function test_the_admin_emails_an_invite_to_an_address(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post('/admin/invites', ['email' => 'Friend@Example.com'])
            ->assertRedirect('/admin/invites');

        Mail::assertQueued(InviteMail::class, fn ($m) => $m->hasTo('friend@example.com'));
    }

    public function test_an_ordinary_user_cannot_send_invites(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/admin/invites', ['email' => 'friend@example.com'])->assertForbidden();

        Mail::assertNothingQueued();
    }

    public function test_sending_marks_the_matching_request_as_invited(): void
    {
        $request = InviteRequest::create(['email' => 'friend@example.com']);

        $this->sendInvite('friend@example.com');

        $this->assertNotNull($request->fresh()->invited_at);
    }

    public function test_opening_the_link_does_not_use_the_code(): void
    {
        $url = $this->sendInvite('friend@example.com');

        $this->get($url)->assertOk();

        $this->assertGuest();
        $this->assertNull(User::where('email', 'friend@example.com')->first());
    }

    public function test_confirming_creates_an_invited_account_and_signs_in(): void
    {
        $url = $this->sendInvite('friend@example.com');

        $this->post($url)->assertRedirect();

        $this->assertAuthenticated();
        $this->assertSame('friend@example.com', auth()->user()->email);
        $this->assertSame('invite', auth()->user()->signup_method);
    }

    public function test_invites_work_after_self_signup_has_closed(): void
    {
        config(['nailpolish.self_signup_limit' => 1]);
        User::factory()->create(['signup_method' => 'self']);
        $url = $this->sendInvite('friend@example.com');

        $this->post($url);

        $this->assertAuthenticated();
    }

    public function test_a_code_works_once(): void
    {
        $url = $this->sendInvite('friend@example.com');
        $this->post($url);
        auth()->logout();

        $this->post($url)->assertRedirect('/login');

        $this->assertGuest();
    }

    public function test_a_code_expires_after_30_days(): void
    {
        $url = $this->sendInvite('friend@example.com');

        $this->travel(31)->days();
        $this->post($url)->assertRedirect('/login');

        $this->assertGuest();
        $this->assertNull(User::where('email', 'friend@example.com')->first());
    }

    public function test_a_made_up_code_is_rejected(): void
    {
        $this->post('/invite/not-a-real-code')->assertRedirect('/login');

        $this->assertGuest();
    }

    public function test_inviting_someone_who_already_has_an_account_is_refused(): void
    {
        User::factory()->create(['email' => 'friend@example.com']);

        $this->actingAs(User::factory()->admin()->create())
            ->post('/admin/invites', ['email' => 'friend@example.com'])
            ->assertSessionHasErrors('email');

        Mail::assertNothingQueued();
    }

    public function test_an_invite_redeemed_after_the_account_exists_signs_in_without_changing_how_they_joined(): void
    {
        $url = $this->sendInvite('friend@example.com');
        User::factory()->create(['email' => 'friend@example.com', 'signup_method' => 'self']);

        $this->post($url);

        $this->assertSame('self', auth()->user()->signup_method);
    }

    public function test_resending_replaces_the_earlier_code(): void
    {
        $first = $this->sendInvite('friend@example.com');
        Mail::fake();
        $second = $this->sendInvite('friend@example.com');

        $this->post($first)->assertRedirect('/login');
        $this->assertGuest();

        $this->post($second);
        $this->assertAuthenticated();
    }

    public function test_a_signed_in_user_can_still_redeem_an_invite_link(): void
    {
        $url = $this->sendInvite('friend@example.com');

        $this->actingAs(User::factory()->create())->post($url);

        $this->assertSame('friend@example.com', auth()->user()->email);
    }
}
