<?php

namespace Tests\Feature;

use App\Models\InviteRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InviteRequestTest extends TestCase
{
    use RefreshDatabase;

    private function closeSignups(): void
    {
        config(['nailpolish.self_signup_limit' => 1]);
        User::factory()->create(['signup_method' => 'self']);
    }

    public function test_while_signups_are_open_there_is_no_request_form(): void
    {
        $this->get('/invite-request')->assertRedirect('/login');
        $this->post('/invite-request', ['email' => 'a@example.com'])->assertRedirect('/login');

        $this->assertSame(0, InviteRequest::count());
    }

    public function test_the_login_page_offers_the_form_once_closed(): void
    {
        $this->get('/login')->assertDontSee('request an invite', false);

        $this->closeSignups();

        $this->get('/login')->assertSee('request an invite', false);
    }

    public function test_once_closed_an_email_can_be_requested(): void
    {
        $this->closeSignups();

        $this->post('/invite-request', ['email' => 'New@Example.com'])
            ->assertRedirect('/invite-request/sent');

        $this->assertSame(['new@example.com'], InviteRequest::pluck('email')->all());
    }

    public function test_repeat_requests_are_stored_once_with_the_same_response(): void
    {
        $this->closeSignups();

        $first = $this->post('/invite-request', ['email' => 'new@example.com']);
        $again = $this->post('/invite-request', ['email' => 'NEW@example.com']);

        $this->assertSame($first->headers->get('Location'), $again->headers->get('Location'));
        $this->assertSame(1, InviteRequest::count());
    }

    public function test_an_existing_users_address_gets_the_same_response_and_is_not_stored(): void
    {
        $this->closeSignups();
        User::factory()->create(['email' => 'here@example.com']);

        $this->post('/invite-request', ['email' => 'here@example.com'])
            ->assertRedirect('/invite-request/sent');

        $this->assertSame(0, InviteRequest::count());
    }

    public function test_an_invalid_email_is_rejected(): void
    {
        $this->closeSignups();

        $this->post('/invite-request', ['email' => 'nope'])->assertSessionHasErrors('email');
    }

    public function test_the_admin_sees_requests_and_others_do_not(): void
    {
        $this->closeSignups();
        InviteRequest::create(['email' => 'new@example.com']);

        $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/invites')->assertOk()->assertSee('new@example.com');

        $this->actingAs(User::factory()->create())->get('/admin/invites')->assertForbidden();
    }
}
