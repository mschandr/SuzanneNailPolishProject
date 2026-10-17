<?php

namespace Tests\Feature;

use App\Models\Bottle;
use App\Models\Brand;
use App\Models\Location;
use App\Models\Polish;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocationsTest extends TestCase
{
    use RefreshDatabase;

    private User $me;

    protected function setUp(): void
    {
        parent::setUp();
        $this->me = User::factory()->create();
    }

    private function bottleIn(User $user, ?Location $location): Bottle
    {
        $polish = Polish::firstOrCreate(
            ['name_key' => 'ember rising'],
            ['brand_id' => Brand::firstOrCreate(['name_key' => 'phoenix parlour'], ['name' => 'Phoenix Parlour'])->id, 'name' => 'Ember Rising'],
        );
        $bottle = new Bottle(['polish_id' => $polish->id, 'location_id' => $location?->id]);
        $bottle->user_id = $user->id;
        $bottle->save();

        return $bottle;
    }

    public function test_a_guest_is_sent_to_sign_in(): void
    {
        $this->get('/locations')->assertRedirect('/login');
    }

    public function test_a_location_can_be_created_on_its_own(): void
    {
        $this->actingAs($this->me)->post('/locations', ['name' => ' Dining room '])->assertRedirect('/locations');

        $this->assertSame(['Dining room'], $this->me->locations()->pluck('name')->all());
    }

    public function test_a_duplicate_name_ignoring_case_is_refused(): void
    {
        $this->me->locations()->create(['name' => 'China cabinet']);

        $this->actingAs($this->me)->post('/locations', ['name' => 'CHINA cabinet'])
            ->assertSessionHasErrors('name');

        $this->assertSame(1, $this->me->locations()->count());
    }

    public function test_two_users_can_each_have_a_location_with_the_same_name(): void
    {
        User::factory()->create()->locations()->create(['name' => 'China cabinet']);

        $this->actingAs($this->me)->post('/locations', ['name' => 'China cabinet'])->assertSessionHasNoErrors();

        $this->assertSame(1, $this->me->locations()->count());
    }

    public function test_a_blank_name_is_refused(): void
    {
        $this->actingAs($this->me)->post('/locations', ['name' => '  '])->assertSessionHasErrors('name');
    }

    public function test_the_list_shows_only_my_locations_with_bottle_counts(): void
    {
        $mine = $this->me->locations()->create(['name' => 'China cabinet']);
        User::factory()->create()->locations()->create(['name' => 'Their secret place']);
        $this->bottleIn($this->me, $mine);
        $this->bottleIn($this->me, $mine);

        $this->actingAs($this->me)->get('/locations')
            ->assertOk()
            ->assertSee('China cabinet')
            ->assertSee('2 bottles')
            ->assertDontSee('Their secret place');
    }

    public function test_a_location_can_be_renamed(): void
    {
        $location = $this->me->locations()->create(['name' => 'Cabinet']);

        $this->actingAs($this->me)->put("/locations/{$location->id}", ['name' => 'China cabinet'])
            ->assertRedirect('/locations');

        $this->assertSame('China cabinet', $location->fresh()->name);
    }

    public function test_renaming_to_another_of_my_locations_names_is_refused_but_recasing_itself_is_fine(): void
    {
        $this->me->locations()->create(['name' => 'Bedroom']);
        $cabinet = $this->me->locations()->create(['name' => 'Cabinet']);

        $this->actingAs($this->me)->put("/locations/{$cabinet->id}", ['name' => 'bedroom'])
            ->assertSessionHasErrors('name');
        $this->actingAs($this->me)->put("/locations/{$cabinet->id}", ['name' => 'CABINET'])
            ->assertSessionHasNoErrors();
    }

    public function test_deleting_a_location_leaves_its_bottles_unplaced(): void
    {
        $location = $this->me->locations()->create(['name' => 'Cabinet']);
        $bottle = $this->bottleIn($this->me, $location);

        $this->actingAs($this->me)->delete("/locations/{$location->id}")->assertRedirect('/locations');

        $this->assertNull(Location::find($location->id));
        $this->assertNotNull($bottle->fresh());
        $this->assertNull($bottle->fresh()->location_id);
    }

    public function test_someone_elses_location_cannot_be_renamed_or_deleted(): void
    {
        $theirs = User::factory()->create()->locations()->create(['name' => 'Theirs']);

        $this->actingAs($this->me)->put("/locations/{$theirs->id}", ['name' => 'Mine now'])->assertNotFound();
        $this->actingAs($this->me)->delete("/locations/{$theirs->id}")->assertNotFound();

        $this->assertSame('Theirs', $theirs->fresh()->name);
    }
}
