<?php

namespace Tests\Feature;

use App\Models\Bottle;
use App\Models\Brand;
use App\Models\Location;
use App\Models\Polish;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarkOwnedTest extends TestCase
{
    use RefreshDatabase;

    private Polish $polish;

    protected function setUp(): void
    {
        parent::setUp();
        $brand = Brand::create(['name' => 'Phoenix Parlour']);
        $this->polish = Polish::create(['brand_id' => $brand->id, 'name' => 'Ember Rising']);
    }

    private function own(User $user, array $data = [])
    {
        return $this->actingAs($user)->post("/polishes/{$this->polish->id}/bottles", $data);
    }

    public function test_a_guest_is_sent_to_sign_in(): void
    {
        $this->post("/polishes/{$this->polish->id}/bottles")->assertRedirect('/login');

        $this->assertSame(0, Bottle::count());
    }

    public function test_marking_owned_creates_an_owned_bottle_for_that_user(): void
    {
        $user = User::factory()->create();

        $this->own($user)->assertRedirect();

        $bottle = Bottle::firstOrFail();
        $this->assertSame($user->id, $bottle->user_id);
        $this->assertSame($this->polish->id, $bottle->polish_id);
        $this->assertSame('owned', $bottle->status);
        $this->assertNull($bottle->location_id);
    }

    public function test_it_can_go_straight_into_an_existing_location(): void
    {
        $user = User::factory()->create();
        $location = $user->locations()->create(['name' => 'China cabinet']);

        $this->own($user, ['location_id' => $location->id]);

        $this->assertSame($location->id, Bottle::firstOrFail()->location_id);
    }

    public function test_a_new_location_can_be_typed_inline(): void
    {
        $user = User::factory()->create();

        $this->own($user, ['new_location' => '  Under the bed ']);

        $location = Location::firstOrFail();
        $this->assertSame('Under the bed', $location->name);
        $this->assertSame($user->id, $location->user_id);
        $this->assertSame($location->id, Bottle::firstOrFail()->location_id);
    }

    public function test_typing_an_existing_locations_name_reuses_it(): void
    {
        $user = User::factory()->create();
        $user->locations()->create(['name' => 'China cabinet']);

        $this->own($user, ['new_location' => 'china CABINET']);

        $this->assertSame(1, Location::count());
        $this->assertNotNull(Bottle::firstOrFail()->location_id);
    }

    public function test_owning_it_again_adds_a_second_bottle(): void
    {
        $user = User::factory()->create();

        $this->own($user);
        $this->own($user);

        $this->assertSame(2, $user->bottles()->count());
    }

    public function test_someone_elses_location_cannot_be_used(): void
    {
        $other = User::factory()->create()->locations()->create(['name' => 'Their cabinet']);

        $this->own(User::factory()->create(), ['location_id' => $other->id])
            ->assertSessionHasErrors('location_id');

        $this->assertSame(0, Bottle::count());
    }

    public function test_an_unknown_polish_is_404(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/polishes/0198aaaa-0000-7000-8000-000000000000/bottles')->assertNotFound();
    }

    public function test_the_polish_page_shows_only_your_own_bottles(): void
    {
        $me = User::factory()->create();
        $you = User::factory()->create();
        $this->own($me);
        $this->own($me);
        $this->own($you);

        $this->actingAs($me)->get($this->polish->fresh('brand')->url())->assertSee('You own 2');
        $this->actingAs($you)->get($this->polish->fresh('brand')->url())->assertSee('You own 1');
    }

    public function test_a_guest_is_invited_to_sign_in_on_the_polish_page(): void
    {
        $this->get($this->polish->fresh('brand')->url())->assertSee('Sign in')->assertDontSee('You own');
    }

    public function test_a_too_long_new_location_name_shows_an_error_on_the_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->from($this->polish->fresh('brand')->url())
            ->post("/polishes/{$this->polish->id}/bottles", ['new_location' => str_repeat('x', 150)])
            ->assertSessionHasErrors('new_location');

        $this->actingAs($user)->get($this->polish->fresh('brand')->url())->assertOk();
        $this->assertSame(0, Bottle::count());
    }

    public function test_the_polish_page_shows_the_error_when_there_is_one(): void
    {
        $user = User::factory()->create();
        $url = $this->polish->fresh('brand')->url();

        $this->actingAs($user)->from($url)
            ->post("/polishes/{$this->polish->id}/bottles", ['new_location' => str_repeat('x', 150)]);

        $this->get($url)->assertSee('must not be greater than 100 characters', false);
    }

    public function test_a_blank_new_location_is_ignored(): void
    {
        $user = User::factory()->create();

        $this->own($user, ['new_location' => '   '])->assertSessionHasNoErrors();

        $this->assertSame(0, Location::count());
        $this->assertSame(1, Bottle::count());
    }

    public function test_choosing_a_location_and_typing_a_new_one_is_refused_rather_than_guessed(): void
    {
        $user = User::factory()->create();
        $location = $user->locations()->create(['name' => 'China cabinet']);

        $this->own($user, ['location_id' => $location->id, 'new_location' => 'Under the bed'])
            ->assertSessionHasErrors('new_location');

        $this->assertSame(0, Bottle::count());
    }
}
