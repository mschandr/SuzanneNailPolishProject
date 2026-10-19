<?php

namespace Tests\Feature;

use App\Models\Manicure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\TestImages;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    private User $suzanne;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('photos');
        $this->suzanne = User::factory()->create(['display_name' => 'Suzanne', 'email' => 'suzanne-secret@example.com']);
    }

    private function manicure(?string $visibility, string $caption, ?User $owner = null): Manicure
    {
        $owner ??= $this->suzanne;
        $response = $this->actingAs($owner)->post('/manicures', [
            'worn_on' => '2026-10-15', 'caption' => $caption, 'visibility' => $visibility,
            'photos' => [TestImages::upload('a.jpg', 400, 300)],
        ]);
        auth()->logout();

        return Manicure::findOrFail(basename($response->headers->get('Location')));
    }

    public function test_anyone_can_see_a_users_public_manicures_and_display_name(): void
    {
        $m = $this->manicure('public', 'Public one');

        $this->get("/people/{$this->suzanne->id}")->assertOk()
            ->assertSee('Suzanne')
            ->assertSee("/manicures/{$m->id}", false);
    }

    public function test_the_profile_never_shows_the_email_address(): void
    {
        $this->manicure('public', 'Public one');

        $this->get("/people/{$this->suzanne->id}")->assertDontSee('suzanne-secret@example.com');
    }

    public function test_private_manicures_are_not_listed_for_others(): void
    {
        $private = $this->manicure('private', 'Hidden one');
        $public = $this->manicure('public', 'Shown one');

        $page = $this->get("/people/{$this->suzanne->id}")->assertOk();
        $page->assertSee("/manicures/{$public->id}", false)->assertDontSee("/manicures/{$private->id}", false);

        $this->actingAs(User::factory()->create())->get("/people/{$this->suzanne->id}")
            ->assertDontSee("/manicures/{$private->id}", false);
    }

    public function test_manicures_following_a_private_account_default_are_not_listed(): void
    {
        $followsDefault = $this->manicure(null, 'Follows default');
        $this->suzanne->forceFill(['manicure_default_visibility' => 'private'])->save();

        $this->get("/people/{$this->suzanne->id}")->assertDontSee("/manicures/{$followsDefault->id}", false);

        $override = $this->manicure('public', 'Override public');
        $this->get("/people/{$this->suzanne->id}")->assertSee("/manicures/{$override->id}", false);
    }

    public function test_the_owner_sees_all_of_their_own_marked_by_privacy(): void
    {
        $private = $this->manicure('private', 'Hidden one');
        $public = $this->manicure('public', 'Shown one');

        $this->actingAs($this->suzanne)->get("/people/{$this->suzanne->id}")->assertOk()
            ->assertSee("/manicures/{$private->id}", false)
            ->assertSee("/manicures/{$public->id}", false)
            ->assertSee('Only you');
    }

    public function test_other_users_manicures_are_not_on_this_profile(): void
    {
        $mine = $this->manicure('public', 'Mine');
        $theirs = $this->manicure('public', 'Theirs', User::factory()->create(['display_name' => 'Zed']));

        $this->get("/people/{$this->suzanne->id}")
            ->assertSee("/manicures/{$mine->id}", false)->assertDontSee("/manicures/{$theirs->id}", false);
    }

    public function test_an_unknown_user_is_404(): void
    {
        $this->get('/people/0198aaaa-0000-7000-8000-000000000000')->assertNotFound();
        $this->get('/people/not-a-uuid')->assertNotFound();
    }

    public function test_a_user_who_has_not_chosen_a_display_name_has_no_public_page(): void
    {
        $nameless = User::factory()->create(['display_name' => null]);

        $this->get("/people/{$nameless->id}")->assertNotFound();
    }

    public function test_the_list_is_paginated_newest_first(): void
    {
        foreach (range(1, 26) as $i) {
            $m = $this->manicure('public', "n{$i}");
            $m->forceFill(['worn_on' => now()->subDays(30 - $i)->toDateString()])->save();
        }
        $newest = Manicure::orderByDesc('worn_on')->first();
        $oldest = Manicure::orderBy('worn_on')->first();

        $this->get("/people/{$this->suzanne->id}")->assertSee("/manicures/{$newest->id}", false)->assertDontSee("/manicures/{$oldest->id}", false);
        $this->get("/people/{$this->suzanne->id}?page=2")->assertSee("/manicures/{$oldest->id}", false);
    }

    public function test_the_manicure_page_links_to_the_owners_profile_not_their_email(): void
    {
        $m = $this->manicure('public', 'Public one');

        $this->get("/manicures/{$m->id}")->assertSee("/people/{$this->suzanne->id}", false)->assertDontSee('suzanne-secret');
    }

    public function test_my_manicures_page_lists_everything_of_mine_and_nothing_of_anyone_elses(): void
    {
        $mineHidden = $this->manicure('private', 'Mine hidden');
        $theirs = $this->manicure('public', 'Theirs', User::factory()->create(['display_name' => 'Zed']));

        $this->actingAs($this->suzanne)->get('/manicures')->assertOk()
            ->assertSee("/manicures/{$mineHidden->id}", false)->assertDontSee("/manicures/{$theirs->id}", false);
    }
}
