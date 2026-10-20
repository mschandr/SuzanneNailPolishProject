<?php

namespace Tests\Feature;

use App\Models\Bottle;
use App\Models\Brand;
use App\Models\Location;
use App\Models\Polish;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CollectionSearchTest extends TestCase
{
    use RefreshDatabase;

    private User $me;

    protected function setUp(): void
    {
        parent::setUp();
        $this->me = User::factory()->create();
    }

    private function bottle(User $user, string $brand, string $shade, ?Location $location = null, string $status = 'owned'): Bottle
    {
        $brandModel = Brand::firstOrCreate(['name_key' => strtolower($brand)], ['name' => $brand]);
        $polish = Polish::firstOrCreate(
            ['brand_id' => $brandModel->id, 'name_key' => strtolower($shade)],
            ['name' => $shade],
        );
        $bottle = new Bottle(['polish_id' => $polish->id, 'location_id' => $location?->id]);
        $bottle->user_id = $user->id;
        $bottle->status = $status;
        $bottle->save();

        return $bottle;
    }

    public function test_it_lists_only_my_owned_bottles(): void
    {
        $this->bottle($this->me, 'Phoenix Parlour', 'Ember Rising');
        $this->bottle(User::factory()->create(), 'Phoenix Parlour', 'Their Secret Shade');

        $this->actingAs($this->me)->get('/collection')
            ->assertOk()
            ->assertSee('Ember Rising')
            ->assertDontSee('Their Secret Shade');
    }

    public function test_history_is_hidden_unless_asked_for(): void
    {
        $this->bottle($this->me, 'Phoenix Parlour', 'Ember Rising');
        $this->bottle($this->me, 'Phoenix Parlour', 'Golden Plume', null, 'used_up');
        $this->bottle($this->me, 'Phoenix Parlour', 'Rebirth', null, 'given_away');
        $this->bottle($this->me, 'Phoenix Parlour', 'Ashes to Ashes', null, 'discarded');

        $this->actingAs($this->me)->get('/collection')
            ->assertSee('Ember Rising')
            ->assertDontSee('Golden Plume')->assertDontSee('Rebirth')->assertDontSee('Ashes to Ashes');

        $this->actingAs($this->me)->get('/collection?history=1')
            ->assertSee('Ember Rising')->assertSee('Golden Plume')->assertSee('Rebirth')->assertSee('Ashes to Ashes');
    }

    public function test_search_matches_shade_or_brand_ignoring_case(): void
    {
        $this->bottle($this->me, 'Phoenix Parlour', 'Ember Rising');
        $this->bottle($this->me, 'Moonlit Menagerie', 'Unicorn Hoof');

        $this->actingAs($this->me)->get('/collection?q=EMBER')
            ->assertSee('Ember Rising')->assertDontSee('Unicorn Hoof');
        $this->actingAs($this->me)->get('/collection?q=moonlit')
            ->assertSee('Unicorn Hoof')->assertDontSee('Ember Rising');
    }

    public function test_wildcard_characters_in_the_search_are_literal(): void
    {
        $this->bottle($this->me, 'Phoenix Parlour', 'Ember Rising');

        $this->actingAs($this->me)->get('/collection?q=%25')->assertDontSee('Ember Rising');
        $this->actingAs($this->me)->get('/collection?q=_')->assertDontSee('Ember Rising');
    }

    public function test_search_never_finds_someone_elses_bottles(): void
    {
        $this->bottle(User::factory()->create(), 'Phoenix Parlour', 'Ember Rising');

        $this->actingAs($this->me)->get('/collection?q=ember')
            ->assertOk()->assertDontSee('Ember Rising')->assertSee('No bottles');
    }

    public function test_it_filters_by_location_and_shows_where_each_bottle_is(): void
    {
        $cabinet = $this->me->locations()->create(['name' => 'China cabinet']);
        $bed = $this->me->locations()->create(['name' => 'Under the bed']);
        $this->bottle($this->me, 'Phoenix Parlour', 'Ember Rising', $cabinet);
        $this->bottle($this->me, 'Phoenix Parlour', 'Golden Plume', $bed);

        $this->actingAs($this->me)->get("/collection?location={$cabinet->id}")
            ->assertSee('Ember Rising')->assertDontSee('Golden Plume');
        $this->actingAs($this->me)->get('/collection')->assertSee('China cabinet')->assertSee('Under the bed');
    }

    public function test_unplaced_bottles_can_be_listed(): void
    {
        $cabinet = $this->me->locations()->create(['name' => 'China cabinet']);
        $this->bottle($this->me, 'Phoenix Parlour', 'Ember Rising', $cabinet);
        $this->bottle($this->me, 'Phoenix Parlour', 'Golden Plume');

        $this->actingAs($this->me)->get('/collection?location=none')
            ->assertSee('Golden Plume')->assertDontSee('Ember Rising');
    }

    public function test_filtering_by_someone_elses_location_shows_nothing_of_theirs(): void
    {
        $other = User::factory()->create();
        $theirs = $other->locations()->create(['name' => 'Theirs']);
        $this->bottle($other, 'Phoenix Parlour', 'Their Secret Shade', $theirs);

        $this->actingAs($this->me)->get("/collection?location={$theirs->id}")
            ->assertDontSee('Their Secret Shade')->assertDontSee('Theirs');
    }

    public function test_a_polish_owned_twice_shows_both_bottles(): void
    {
        $cabinet = $this->me->locations()->create(['name' => 'China cabinet']);
        $bed = $this->me->locations()->create(['name' => 'Under the bed']);
        $this->bottle($this->me, 'Phoenix Parlour', 'Ember Rising', $cabinet);
        $this->bottle($this->me, 'Phoenix Parlour', 'Ember Rising', $bed);

        $this->actingAs($this->me)->get('/collection?q=ember')
            ->assertSee('China cabinet')->assertSee('Under the bed');
    }

    public function test_the_results_say_how_many_bottles_matched(): void
    {
        $this->bottle($this->me, 'Phoenix Parlour', 'Ember Rising');
        $this->bottle($this->me, 'Phoenix Parlour', 'Ember Rising');

        $this->actingAs($this->me)->get('/collection?q=ember')->assertSee('2 bottles');
    }

    public function test_a_long_collection_is_paginated(): void
    {
        foreach (range(1, 60) as $i) {
            $this->bottle($this->me, 'Phoenix Parlour', sprintf('Shade %02d', $i));
        }

        $this->actingAs($this->me)->get('/collection')->assertSee('Shade 50')->assertDontSee('Shade 51');
        $this->actingAs($this->me)->get('/collection?page=2')->assertSee('Shade 51');
    }

    public function test_array_query_parameters_do_not_crash_the_page(): void
    {
        $this->actingAs($this->me)->get('/collection?q[]=x')->assertOk();
        $this->actingAs($this->me)->get('/collection?location[]=1&history[]=1')->assertOk();
    }
}
