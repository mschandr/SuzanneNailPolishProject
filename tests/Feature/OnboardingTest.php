<?php

namespace Tests\Feature;

use App\Mail\MagicLinkMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class OnboardingTest extends TestCase
{
    use RefreshDatabase;

    private function newcomer(): User
    {
        return User::factory()->notOnboarded()->create();
    }

    public function test_a_new_user_landing_on_home_is_sent_to_the_tutorial(): void
    {
        $this->actingAs($this->newcomer())->get('/home')->assertRedirect('/welcome');
    }

    public function test_the_tour_is_offered_only_once_even_if_the_user_wanders_off_without_skipping(): void
    {
        $user = $this->newcomer();

        $this->actingAs($user)->get('/home')->assertRedirect('/welcome');
        // They click a call-to-action instead of Skip, then come back to /home later.
        $this->actingAs($user->fresh())->get('/catalog')->assertOk();
        $this->actingAs($user->fresh())->get('/home')->assertOk();
    }

    public function test_a_user_who_finished_or_skipped_is_not_sent_again(): void
    {
        $this->actingAs(User::factory()->create())->get('/home')->assertOk();
    }

    public function test_a_brand_new_user_ends_up_in_the_tutorial_after_choosing_a_display_name(): void
    {
        Mail::fake();
        $this->post('/login', ['email' => 'suzanne@example.com']);
        $url = null;
        Mail::assertQueued(MagicLinkMail::class, function ($m) use (&$url) {
            $url = $m->url;

            return true;
        });
        $this->post($url);

        $this->post('/display-name', ['display_name' => 'Suzanne'])->assertRedirect('/home');
        $this->get('/home')->assertRedirect('/welcome');
    }

    public function test_the_tutorial_starts_at_step_one_and_has_four_steps(): void
    {
        $user = $this->newcomer();

        $this->actingAs($user)->get('/welcome')->assertOk()->assertSee('Step 1 of 4');
        foreach ([2, 3, 4] as $step) {
            $this->actingAs($user)->get("/welcome?step={$step}")->assertSee("Step {$step} of 4");
        }
    }

    public function test_a_bad_step_number_falls_back_to_step_one(): void
    {
        $user = $this->newcomer();

        foreach (['0', '9', '-1', 'abc', '2abc'] as $bad) {
            $this->actingAs($user)->get("/welcome?step={$bad}")->assertOk()->assertSee('Step 1 of 4');
        }
        $this->actingAs($user)->get('/welcome?step[]=2')->assertOk()->assertSee('Step 1 of 4');
    }

    public function test_each_step_points_at_the_right_page(): void
    {
        $user = $this->newcomer();

        $this->actingAs($user)->get('/welcome?step=1')->assertSee('href="/catalog"', false);
        $this->actingAs($user)->get('/welcome?step=2')->assertSee('href="/locations"', false);
        $this->actingAs($user)->get('/welcome?step=3')->assertSee('href="/collection"', false);
        $this->actingAs($user)->get('/welcome?step=4')->assertSee('href="/my-polishes"', false);
    }

    public function test_navigation_between_steps_is_offered(): void
    {
        $user = $this->newcomer();

        $first = $this->actingAs($user)->get('/welcome?step=1');
        $first->assertSee('href="/welcome?step=2"', false)->assertDontSee('Back');

        $middle = $this->actingAs($user)->get('/welcome?step=2');
        $middle->assertSee('href="/welcome?step=1"', false)->assertSee('href="/welcome?step=3"', false);

        $last = $this->actingAs($user)->get('/welcome?step=4');
        $last->assertSee('href="/welcome?step=3"', false)->assertSee('Finish');
    }

    public function test_every_step_can_end_the_tour_skip_on_the_early_steps_and_finish_on_the_last(): void
    {
        $user = $this->newcomer();

        foreach ([1, 2, 3] as $step) {
            $this->actingAs($user)->get("/welcome?step={$step}")
                ->assertSee('action="/welcome/complete"', false)->assertSee('Skip the tour');
        }
        $this->actingAs($user)->get('/welcome?step=4')
            ->assertSee('action="/welcome/complete"', false)->assertSee('Finish')->assertDontSee('Skip the tour');
    }

    public function test_skipping_or_finishing_marks_it_done_and_goes_home(): void
    {
        $user = $this->newcomer();

        $this->actingAs($user)->post('/welcome/complete')->assertRedirect('/home');

        $this->assertNotNull($user->fresh()->onboarding_completed_at);
        $this->actingAs($user->fresh())->get('/home')->assertOk();
    }

    public function test_completing_twice_keeps_the_first_time(): void
    {
        $user = $this->newcomer();
        $this->actingAs($user)->post('/welcome/complete');
        $first = $user->fresh()->onboarding_completed_at;

        $this->travel(5)->minutes();
        $this->actingAs($user->fresh())->post('/welcome/complete')->assertRedirect('/home');

        $this->assertEquals($first, $user->fresh()->onboarding_completed_at);
    }

    public function test_replaying_does_not_change_anything(): void
    {
        $user = User::factory()->create();
        $done = $user->onboarding_completed_at;

        $this->actingAs($user)->get('/welcome?step=2')->assertOk();

        $this->assertEquals($done, $user->fresh()->onboarding_completed_at);
        $this->actingAs($user->fresh())->get('/home')->assertOk();
    }

    public function test_the_help_page_links_to_the_tour(): void
    {
        $this->actingAs(User::factory()->create())->get('/help')
            ->assertOk()->assertSee('href="/welcome"', false);
    }

    public function test_help_is_in_the_navigation_for_signed_in_users(): void
    {
        $this->actingAs(User::factory()->create())->get('/catalog')->assertSee('href="/help"', false);
    }

    public function test_a_user_without_a_display_name_is_still_asked_for_one_first(): void
    {
        $user = User::factory()->notOnboarded()->create(['display_name' => null]);

        $this->actingAs($user)->get('/home')->assertRedirect('/display-name');
    }

    public function test_onboarding_status_cannot_be_set_through_a_request(): void
    {
        $user = $this->newcomer();

        $this->actingAs($user)
            ->post('/display-name', ['display_name' => 'Newcomer', 'onboarding_completed_at' => now()->toDateTimeString()])
            ->assertRedirect('/home');

        // The request was accepted and the allowed field saved; the smuggled one was ignored.
        $this->assertSame('Newcomer', $user->fresh()->display_name);
        $this->assertNull($user->fresh()->onboarding_completed_at);
    }
}
