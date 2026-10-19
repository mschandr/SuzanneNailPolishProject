<?php

namespace Tests\Feature;

use App\Models\Heart;
use App\Models\Manicure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsGalleryData;
use Tests\TestCase;

class HeartsTest extends TestCase
{
    use BuildsGalleryData, RefreshDatabase;

    private User $owner;

    private User $fan;

    private Manicure $manicure;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = $this->person('Suzanne');
        $this->fan = $this->person('Fan');
        $this->manicure = $this->manicure($this->owner, [], 'public');
    }

    private function hearts(): int
    {
        return Heart::where('manicure_id', $this->manicure->id)->count();
    }

    public function test_a_guest_is_sent_to_sign_in(): void
    {
        $this->post("/manicures/{$this->manicure->id}/heart")->assertRedirect('/login');
        $this->delete("/manicures/{$this->manicure->id}/heart")->assertRedirect('/login');
        $this->get('/liked')->assertRedirect('/login');

        $this->assertSame(0, $this->hearts());
    }

    public function test_a_user_can_heart_someone_elses_public_manicure(): void
    {
        $this->actingAs($this->fan)->post("/manicures/{$this->manicure->id}/heart")->assertRedirect();

        $this->assertSame(1, $this->hearts());
        $this->assertTrue(Heart::where('user_id', $this->fan->id)->where('manicure_id', $this->manicure->id)->exists());
    }

    public function test_hearting_twice_still_counts_once(): void
    {
        $this->actingAs($this->fan)->post("/manicures/{$this->manicure->id}/heart");
        $this->actingAs($this->fan)->post("/manicures/{$this->manicure->id}/heart")->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(1, $this->hearts());
    }

    public function test_different_users_each_add_one(): void
    {
        $this->actingAs($this->fan)->post("/manicures/{$this->manicure->id}/heart");
        $this->actingAs($this->person('Other'))->post("/manicures/{$this->manicure->id}/heart");

        $this->assertSame(2, $this->hearts());
    }

    public function test_you_cannot_heart_your_own_manicure(): void
    {
        $this->actingAs($this->owner)->post("/manicures/{$this->manicure->id}/heart")->assertSessionHasErrors('heart');

        $this->assertSame(0, $this->hearts());
    }

    public function test_a_private_manicure_cannot_be_hearted_and_answers_404(): void
    {
        $private = $this->manicure($this->owner, [], 'private');

        $this->actingAs($this->fan)->post("/manicures/{$private->id}/heart")->assertNotFound();

        $this->assertSame(0, Heart::count());
    }

    public function test_an_unknown_manicure_is_404(): void
    {
        $this->actingAs($this->fan)->post('/manicures/0198aaaa-0000-7000-8000-000000000000/heart')->assertNotFound();
    }

    public function test_a_heart_can_be_taken_back_and_taking_back_twice_is_harmless(): void
    {
        $this->actingAs($this->fan)->post("/manicures/{$this->manicure->id}/heart");

        $this->actingAs($this->fan)->delete("/manicures/{$this->manicure->id}/heart")->assertRedirect();
        $this->actingAs($this->fan)->delete("/manicures/{$this->manicure->id}/heart")->assertRedirect();

        $this->assertSame(0, $this->hearts());
    }

    public function test_taking_back_my_heart_does_not_remove_anyone_elses(): void
    {
        $other = $this->person('Other');
        $this->heart($other, $this->manicure);
        $this->heart($this->fan, $this->manicure);

        $this->actingAs($this->fan)->delete("/manicures/{$this->manicure->id}/heart");

        $this->assertSame(1, $this->hearts());
        $this->assertTrue(Heart::where('user_id', $other->id)->exists());
    }

    public function test_the_manicure_page_shows_the_count_and_the_right_button(): void
    {
        $this->heart($this->person('A'), $this->manicure);
        $this->heart($this->person('B'), $this->manicure);

        $this->actingAs($this->fan)->get("/manicures/{$this->manicure->id}")
            ->assertSee('2 hearts')->assertSee('action="/manicures/'.$this->manicure->id.'/heart"', false)->assertSee('Heart this');

        $this->heart($this->fan, $this->manicure);
        $this->actingAs($this->fan)->get("/manicures/{$this->manicure->id}")->assertSee('3 hearts')->assertSee('Remove heart');
    }

    public function test_the_owner_sees_the_count_but_no_heart_button(): void
    {
        $this->heart($this->fan, $this->manicure);

        $this->actingAs($this->owner)->get("/manicures/{$this->manicure->id}")
            ->assertSee('1 heart')->assertDontSee('Heart this')->assertDontSee('Remove heart');
    }

    public function test_a_guest_sees_the_count_and_an_invitation_to_sign_in(): void
    {
        $this->heart($this->fan, $this->manicure);

        $this->get("/manicures/{$this->manicure->id}")->assertSee('1 heart')->assertSee('Sign in to heart');
    }

    public function test_deleting_the_manicure_removes_its_hearts(): void
    {
        $this->heart($this->fan, $this->manicure);

        $this->manicure->delete();

        $this->assertSame(0, Heart::count());
    }

    public function test_deleting_a_user_removes_the_hearts_they_gave(): void
    {
        $this->heart($this->fan, $this->manicure);

        $this->fan->delete();

        $this->assertSame(0, Heart::count());
    }

    // --- liked list ---------------------------------------------------------

    public function test_the_liked_list_shows_what_i_hearted_most_recently_first(): void
    {
        $first = $this->manicure($this->owner, [], 'public');
        $second = $this->manicure($this->owner, [], 'public');
        \Illuminate\Support\Facades\DB::table('hearts')->insert(['user_id' => $this->fan->id, 'manicure_id' => $first->id, 'created_at' => now()->subDay()]);
        \Illuminate\Support\Facades\DB::table('hearts')->insert(['user_id' => $this->fan->id, 'manicure_id' => $second->id, 'created_at' => now()]);

        $this->actingAs($this->fan)->get('/liked')->assertOk()
            ->assertSeeInOrder([$this->link($second), $this->link($first)], false)
            ->assertDontSee($this->link($this->manicure), false);
    }

    public function test_the_liked_list_is_only_mine(): void
    {
        $this->heart($this->person('Other'), $this->manicure);

        $this->actingAs($this->fan)->get('/liked')->assertOk()->assertDontSee($this->link($this->manicure), false);
    }

    public function test_a_liked_manicure_that_goes_private_drops_off_and_returns_when_public_again(): void
    {
        $this->heart($this->fan, $this->manicure);
        $this->actingAs($this->fan)->get('/liked')->assertSee($this->link($this->manicure), false);

        $this->manicure->forceFill(['visibility' => 'private'])->save();
        $this->actingAs($this->fan)->get('/liked')->assertDontSee($this->link($this->manicure), false);

        $this->manicure->forceFill(['visibility' => 'public'])->save();
        $this->actingAs($this->fan)->get('/liked')->assertSee($this->link($this->manicure), false);
    }

    public function test_hearts_on_a_private_manicure_are_not_counted_in_the_public_view(): void
    {
        $this->heart($this->fan, $this->manicure);
        $this->manicure->forceFill(['visibility' => 'private'])->save();

        $this->get('/gallery?sort=loved')->assertDontSee($this->link($this->manicure), false);
    }

    public function test_the_liked_list_says_so_when_empty_and_is_paginated(): void
    {
        $this->actingAs($this->fan)->get('/liked')->assertSee('not hearted');

        foreach (range(1, 26) as $i) {
            $this->heart($this->fan, $this->manicure($this->owner, [], 'public', now()->subDays($i)->toDateString()));
        }
        $this->actingAs($this->fan)->get('/liked')->assertSee('page=2', false);
    }

    public function test_the_nav_has_a_liked_link_for_signed_in_users(): void
    {
        $this->actingAs($this->fan)->get('/gallery')->assertSee('href="/liked"', false);
    }

    public function test_hearting_is_rate_limited(): void
    {
        $last = null;
        foreach (range(1, 61) as $_) {
            $last = $this->actingAs($this->fan)->post("/manicures/{$this->manicure->id}/heart");
        }

        $last->assertStatus(429);
    }

    public function test_the_manicure_page_loads_hearts_without_extra_queries_per_heart(): void
    {
        foreach (range(1, 8) as $i) {
            $this->heart($this->person("Fan{$i}"), $this->manicure);
        }

        \Illuminate\Support\Facades\DB::flushQueryLog();
        \Illuminate\Support\Facades\DB::enableQueryLog();
        $this->actingAs($this->fan)->get("/manicures/{$this->manicure->id}")->assertOk()->assertSee('8 hearts');
        $heartQueries = collect(\Illuminate\Support\Facades\DB::getQueryLog())->filter(fn ($q) => str_contains($q['query'], '"hearts"'))->count();
        \Illuminate\Support\Facades\DB::disableQueryLog();

        $this->assertLessThanOrEqual(1, $heartQueries, 'count and viewer state come from the one manicure query');
    }
}
