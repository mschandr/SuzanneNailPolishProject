<?php

namespace Tests\Feature;

use App\Models\Bottle;
use App\Models\Brand;
use App\Models\Location;
use App\Models\Polish;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class BulkOwnTest extends TestCase
{
    use RefreshDatabase;

    private User $me;

    private Brand $brand;

    /** @var array<int, Polish> */
    private array $polishes = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->me = User::factory()->create();
        $this->brand = Brand::create(['name' => 'Phoenix Parlour']);
        foreach (['Ember Rising', 'Golden Plume', 'Rebirth', 'Ashes to Ashes'] as $name) {
            $this->polishes[] = Polish::create(['brand_id' => $this->brand->id, 'name' => $name]);
        }
    }

    private function ids(int ...$indexes): array
    {
        return array_map(fn ($i) => $this->polishes[$i]->id, $indexes);
    }

    private function bulk(array $data, ?User $user = null)
    {
        return $this->actingAs($user ?? $this->me)->post('/bulk/own', $data);
    }

    private function bottle(User $user, Polish $polish, string $status = 'owned'): Bottle
    {
        $bottle = new Bottle(['polish_id' => $polish->id]);
        $bottle->user_id = $user->id;
        $bottle->status = $status;
        $bottle->save();

        return $bottle;
    }

    public function test_selected_polishes_become_owned_bottles_in_the_chosen_location(): void
    {
        $cabinet = $this->me->locations()->create(['name' => 'China cabinet']);

        $this->bulk(['polish_ids' => $this->ids(0, 1, 2), 'location_id' => $cabinet->id])->assertRedirect();

        $this->assertSame(3, $this->me->bottles()->count());
        $this->assertSame(3, $this->me->bottles()->where('location_id', $cabinet->id)->where('status', 'owned')->count());
        $this->assertEqualsCanonicalizing($this->ids(0, 1, 2), $this->me->bottles()->pluck('polish_id')->all());
    }

    public function test_the_location_is_optional(): void
    {
        $this->bulk(['polish_ids' => $this->ids(0, 1)]);

        $this->assertSame(2, $this->me->bottles()->whereNull('location_id')->count());
    }

    public function test_a_new_location_is_created_once_and_shared_by_all_the_bottles(): void
    {
        $this->bulk(['polish_ids' => $this->ids(0, 1, 2), 'new_location' => ' Under the bed ']);

        $this->assertSame(1, Location::count());
        $location = Location::firstOrFail();
        $this->assertSame('Under the bed', $location->name);
        $this->assertSame(3, $this->me->bottles()->where('location_id', $location->id)->count());
    }

    public function test_typing_an_existing_locations_name_reuses_it(): void
    {
        $this->me->locations()->create(['name' => 'China cabinet']);

        $this->bulk(['polish_ids' => $this->ids(0), 'new_location' => 'CHINA cabinet']);

        $this->assertSame(1, Location::count());
    }

    public function test_already_owned_polishes_are_skipped_not_duplicated_and_the_user_is_told(): void
    {
        $this->bottle($this->me, $this->polishes[0]);
        $cabinet = $this->me->locations()->create(['name' => 'China cabinet']);

        $this->bulk(['polish_ids' => $this->ids(0, 1), 'location_id' => $cabinet->id])
            ->assertSessionHas('status', 'Added 1 bottle. 1 was already in your collection and was skipped.');

        $this->assertSame(1, $this->me->bottles()->where('polish_id', $this->polishes[0]->id)->count());
        $this->assertSame(1, $this->me->bottles()->where('polish_id', $this->polishes[1]->id)->count());
    }

    public function test_a_skipped_bottle_keeps_its_existing_location(): void
    {
        $bed = $this->me->locations()->create(['name' => 'Under the bed']);
        $cabinet = $this->me->locations()->create(['name' => 'China cabinet']);
        $existing = $this->bottle($this->me, $this->polishes[0]);
        $existing->forceFill(['location_id' => $bed->id])->save();

        $this->bulk(['polish_ids' => $this->ids(0), 'location_id' => $cabinet->id]);

        $this->assertSame($bed->id, $existing->fresh()->location_id);
    }

    public function test_a_polish_only_in_history_gets_a_new_owned_bottle(): void
    {
        $this->bottle($this->me, $this->polishes[0], 'used_up');

        $this->bulk(['polish_ids' => $this->ids(0)]);

        $this->assertSame(1, $this->me->bottles()->where('status', 'owned')->count());
        $this->assertSame(2, $this->me->bottles()->count());
    }

    public function test_the_message_when_everything_was_already_owned(): void
    {
        $this->bottle($this->me, $this->polishes[0]);

        $this->bulk(['polish_ids' => $this->ids(0)])
            ->assertSessionHas('status', 'Nothing added. 1 was already in your collection.');
    }

    public function test_other_users_bottles_do_not_count_as_mine(): void
    {
        $this->bottle(User::factory()->create(), $this->polishes[0]);

        $this->bulk(['polish_ids' => $this->ids(0)]);

        $this->assertSame(1, $this->me->bottles()->count());
    }

    public function test_one_unknown_id_means_nothing_is_created(): void
    {
        $this->bulk(['polish_ids' => [...$this->ids(0, 1), '0198aaaa-0000-7000-8000-000000000000']])
            ->assertSessionHasErrors('polish_ids');

        $this->assertSame(0, Bottle::count());
    }

    public function test_someone_elses_private_polish_cannot_be_included_and_nothing_is_created(): void
    {
        $theirs = new Polish(['brand_id' => $this->brand->id, 'name' => 'Their Secret']);
        $theirs->user_id = User::factory()->create()->id;
        $theirs->save();

        $this->bulk(['polish_ids' => [...$this->ids(0), $theirs->id]])->assertSessionHasErrors('polish_ids');

        $this->assertSame(0, Bottle::count());
    }

    public function test_my_own_private_polish_can_be_included(): void
    {
        $mine = new Polish(['brand_id' => $this->brand->id, 'name' => 'My Secret']);
        $mine->user_id = $this->me->id;
        $mine->save();

        $this->bulk(['polish_ids' => [$mine->id]]);

        $this->assertSame(1, $this->me->bottles()->count());
    }

    public function test_someone_elses_location_means_nothing_is_created(): void
    {
        $theirs = User::factory()->create()->locations()->create(['name' => 'Theirs']);

        $this->bulk(['polish_ids' => $this->ids(0, 1), 'location_id' => $theirs->id])
            ->assertSessionHasErrors('location_id');

        $this->assertSame(0, Bottle::count());
    }

    public function test_at_least_one_polish_is_required(): void
    {
        $this->bulk(['polish_ids' => []])->assertSessionHasErrors('polish_ids');
        $this->bulk([])->assertSessionHasErrors('polish_ids');
    }

    public function test_more_than_100_in_one_go_is_refused(): void
    {
        $ids = array_map(fn () => (string) Str::uuid(), range(1, 101));

        $this->bulk(['polish_ids' => $ids])->assertSessionHasErrors('polish_ids');
    }

    public function test_repeated_ids_in_the_request_create_one_bottle(): void
    {
        $this->bulk(['polish_ids' => [$this->polishes[0]->id, $this->polishes[0]->id]]);

        $this->assertSame(1, $this->me->bottles()->count());
    }

    public function test_the_brand_page_offers_selection_only_to_signed_in_users(): void
    {
        $this->get('/catalog/phoenix-parlour')->assertOk()->assertDontSee('name="polish_ids[]"', false);

        $this->actingAs($this->me)->get('/catalog/phoenix-parlour')
            ->assertSee('name="polish_ids[]"', false)
            ->assertSee('action="/bulk/own"', false);
    }

    public function test_the_brand_page_lists_only_my_locations_in_the_picker(): void
    {
        $this->me->locations()->create(['name' => 'China cabinet']);
        User::factory()->create()->locations()->create(['name' => 'Their secret place']);

        $this->actingAs($this->me)->get('/catalog/phoenix-parlour')
            ->assertSee('China cabinet')->assertDontSee('Their secret place');
    }

    public function test_choosing_a_location_and_typing_a_new_one_is_refused_rather_than_guessed(): void
    {
        $cabinet = $this->me->locations()->create(['name' => 'China cabinet']);

        $this->bulk(['polish_ids' => $this->ids(0), 'location_id' => $cabinet->id, 'new_location' => 'Under the bed'])
            ->assertSessionHasErrors('new_location');

        $this->assertSame(0, Bottle::count());
        $this->assertSame(1, Location::count());
    }

    public function test_a_failed_submit_keeps_the_ticked_boxes_and_typed_location(): void
    {
        $url = '/catalog/phoenix-parlour';
        $this->actingAs($this->me)->from($url)
            ->post('/bulk/own', ['polish_ids' => [$this->polishes[1]->id], 'new_location' => str_repeat('x', 150)]);

        $html = $this->actingAs($this->me)->get($url)->getContent();

        $this->assertMatchesRegularExpression('/value="'.$this->polishes[1]->id.'"[^>]*checked/', $html);
        $this->assertDoesNotMatchRegularExpression('/value="'.$this->polishes[0]->id.'"[^>]*checked/', $html);
        $this->assertStringContainsString(str_repeat('x', 150), $html);
    }

    public function test_a_location_is_not_created_when_nothing_is_added(): void
    {
        $this->bottle($this->me, $this->polishes[0]);

        $this->bulk(['polish_ids' => $this->ids(0), 'new_location' => 'Pointless place']);

        $this->assertSame(0, Location::count());
    }
}
