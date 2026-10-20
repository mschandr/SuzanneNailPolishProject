<?php

namespace Tests\Feature;

use App\Mail\MagicLinkMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MagicLinkRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_requesting_a_link_emails_it_to_that_address(): void
    {
        Mail::fake();

        $this->post('/login', ['email' => 'suzanne@example.com'])
            ->assertRedirect('/login/sent');

        Mail::assertQueued(MagicLinkMail::class, fn ($m) => $m->hasTo('suzanne@example.com'));
    }

    public function test_the_response_is_the_same_for_a_known_and_an_unknown_address(): void
    {
        Mail::fake();
        User::factory()->create(['email' => 'known@example.com']);

        $known = $this->post('/login', ['email' => 'known@example.com']);
        $unknown = $this->post('/login', ['email' => 'nobody@example.com']);

        $this->assertSame($known->headers->get('Location'), $unknown->headers->get('Location'));
    }

    public function test_an_invalid_email_is_rejected(): void
    {
        Mail::fake();

        $this->post('/login', ['email' => 'not-an-email'])->assertSessionHasErrors('email');

        Mail::assertNothingSent();
    }
}
