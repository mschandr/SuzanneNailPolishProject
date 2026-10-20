<?php

namespace Tests\Feature;

use App\Mail\MagicLinkMail;
use App\Models\Invite;
use App\Models\ModerationLog;
use App\Models\Report;
use App\Models\User;
use App\Support\SelfSignup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Support\BuildsGalleryData;
use Tests\TestCase;

class ModerationSuspendTest extends TestCase
{
    use BuildsGalleryData, RefreshDatabase;

    private User $admin;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create(['display_name' => 'Boss']);
        $this->owner = $this->person('Suzanne');
    }

    private function suspend(?User $target = null, ?string $reason = 'Repeated spam')
    {
        return $this->actingAs($this->admin)->post('/admin/users/'.($target ?? $this->owner)->id.'/suspend', ['reason' => $reason]);
    }

    public function test_suspending_records_when_and_why_and_is_logged(): void
    {
        $this->suspend()->assertRedirect('/admin/moderation');

        $fresh = $this->owner->fresh();
        $this->assertTrue($fresh->isSuspended());
        $this->assertSame('Repeated spam', $fresh->suspended_reason);
        $entry = ModerationLog::firstOrFail();
        $this->assertSame('suspend', $entry->action);
        $this->assertSame($this->owner->id, $entry->target_id);
        $this->assertSame('Repeated spam', $entry->note);
    }

    public function test_an_admin_cannot_be_suspended_and_neither_can_yourself(): void
    {
        $other = User::factory()->admin()->create();

        $this->suspend($other)->assertSessionHasErrors('suspend');
        $this->suspend($this->admin)->assertSessionHasErrors('suspend');

        $this->assertFalse($other->fresh()->isSuspended());
        $this->assertFalse($this->admin->fresh()->isSuspended());
    }

    public function test_a_suspended_user_cannot_complete_a_magic_link_sign_in(): void
    {
        // A link requested before the suspension must stop working once it is in force.
        Mail::fake();
        $this->post('/login', ['email' => $this->owner->email]);
        $url = null;
        Mail::assertQueued(MagicLinkMail::class, function ($m) use (&$url) {
            $url = $m->url;

            return true;
        });
        $this->suspend();
        auth()->logout();

        $this->post($url)->assertRedirect('/login')->assertSessionHasErrors(['email' => 'This account has been suspended.']);

        $this->assertGuest();
    }

    public function test_a_suspended_user_cannot_redeem_an_invite(): void
    {
        $this->suspend();
        auth()->logout();
        Mail::fake();
        $this->actingAs($this->admin)->post('/admin/invites', ['email' => 'someone@example.com']);
        auth()->logout();
        // An invite sent to the suspended user's own address, from before they were suspended.
        $invite = new Invite;
        $invite->forceFill(['email' => $this->owner->email, 'token_hash' => hash('sha256', 'tok'), 'expires_at' => now()->addDay()])->save();

        $this->post('/invite/tok')->assertRedirect('/login')->assertSessionHasErrors(['email' => 'This account has been suspended.']);

        $this->assertGuest();
    }

    public function test_an_active_session_ends_at_the_next_request(): void
    {
        $this->actingAs($this->owner)->get('/home')->assertOk();

        $this->owner->forceFill(['suspended_at' => now()])->save();

        $this->actingAs($this->owner->fresh())->get('/home')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_a_suspended_users_public_content_disappears_everywhere(): void
    {
        $polish = $this->polish('ILNP', 'Aurora');
        $m = $this->manicure($this->owner, [$polish], 'public');
        $fan = $this->person('Fan');
        $this->heart($fan, $m);
        $url = $this->link($m);
        $this->get($url)->assertOk();

        $this->suspend();
        auth()->logout();

        $this->get('/gallery')->assertDontSee($url, false);
        $this->get($polish->fresh('brand')->url())->assertDontSee($url, false);
        $this->get('/credits')->assertDontSee($url, false);
        $this->get($url)->assertNotFound();
        $this->get("/people/{$this->owner->id}")->assertNotFound();
        $this->get("/photos/{$m->photos->first()->id}/thumb")->assertNotFound();
        $this->actingAs($fan)->get('/liked')->assertDontSee($url, false);
        $this->actingAs($fan)->post("{$url}/heart")->assertNotFound();
    }

    public function test_unsuspending_restores_everything(): void
    {
        $m = $this->manicure($this->owner, [], 'public');
        $this->suspend();
        auth()->logout();
        $this->get($this->link($m))->assertNotFound();

        $this->actingAs($this->admin)->post("/admin/users/{$this->owner->id}/unsuspend")->assertRedirect('/admin/moderation');
        auth()->logout();

        $fresh = $this->owner->fresh();
        $this->assertFalse($fresh->isSuspended());
        $this->assertNull($fresh->suspended_reason);
        $this->get($this->link($m))->assertOk();
        $this->get("/people/{$this->owner->id}")->assertOk();
        $this->assertSame(['suspend', 'unsuspend'], ModerationLog::orderBy('id')->pluck('action')->all());
    }

    public function test_unsuspending_someone_not_suspended_is_404(): void
    {
        $this->actingAs($this->admin)->post("/admin/users/{$this->owner->id}/unsuspend")->assertNotFound();
    }

    public function test_suspended_users_are_listed_so_they_can_be_restored(): void
    {
        $this->suspend();

        $this->actingAs($this->admin)->get('/admin/moderation')->assertSee('Suspended users')->assertSee('Suzanne')->assertSee('Repeated spam')
            ->assertSee('action="/admin/users/'.$this->owner->id.'/unsuspend"', false);
    }

    public function test_the_queue_offers_to_suspend_the_owner_of_a_reported_manicure(): void
    {
        $m = $this->manicure($this->owner, [], 'public');
        $r = new Report(['reason' => 'spam']);
        $r->manicure_id = $m->id;
        $r->reporter_id = $this->person('R')->id;
        $r->status = 'open';
        $r->save();

        $this->actingAs($this->admin)->get('/admin/moderation')
            ->assertSee('action="/admin/users/'.$this->owner->id.'/suspend"', false)
            ->assertSee('action="/admin/manicures/'.$m->id.'/hide"', false)
            ->assertSee('Delete');
    }

    public function test_a_suspended_account_does_not_count_against_the_self_signup_total_changes(): void
    {
        config(['nailpolish.self_signup_limit' => 1]);
        $self = User::factory()->create(['signup_method' => 'self']);
        $this->suspend($self);

        // Suspending must not free the place: someone else is still locked out of self-signup.
        $this->assertFalse(SelfSignup::isOpen());
    }

    public function test_hiding_and_suspension_are_independent(): void
    {
        $m = $this->manicure($this->owner, [], 'public');
        $m->forceFill(['hidden_at' => now()])->save();
        $this->suspend();
        $this->actingAs($this->admin)->post("/admin/users/{$this->owner->id}/unsuspend");
        auth()->logout();

        $this->get($this->link($m))->assertNotFound(); // still hidden
    }

    public function test_a_suspended_user_is_not_sent_sign_in_emails_but_sees_the_same_response(): void
    {
        $this->suspend();
        auth()->logout();
        Mail::fake();

        $suspended = $this->post('/login', ['email' => $this->owner->email]);
        $other = $this->post('/login', ['email' => $this->person('Fine')->email]);

        Mail::assertQueued(MagicLinkMail::class, 1);
        Mail::assertNotQueued(MagicLinkMail::class, fn ($m) => $m->hasTo($this->owner->email));
        $this->assertSame($other->headers->get('Location'), $suspended->headers->get('Location'));
    }
}
