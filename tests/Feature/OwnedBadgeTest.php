<?php

namespace Tests\Feature;

use App\Models\Bottle;
use App\Models\Brand;
use App\Models\Polish;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OwnedBadgeTest extends TestCase
{
    use RefreshDatabase;

    private Polish $ember;

    private Polish $plume;

    protected function setUp(): void
    {
        parent::setUp();
        $brand = Brand::create(['name' => 'Phoenix Parlour']);
        $this->ember = Polish::create(['brand_id' => $brand->id, 'name' => 'Ember Rising']);
        $this->plume = Polish::create(['brand_id' => $brand->id, 'name' => 'Golden Plume']);
    }

    private function bottle(User $user, Polish $polish, string $status = 'owned'): void
    {
        $bottle = new Bottle(['polish_id' => $polish->id]);
        $bottle->user_id = $user->id;
        $bottle->status = $status;
        $bottle->save();
    }

    public function test_owned_polishes_are_badged_and_others_are_not(): void
    {
        $me = User::factory()->create();
        $this->bottle($me, $this->ember);

        $html = $this->actingAs($me)->get('/catalog/phoenix-parlour')->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'data-owned'));
        $this->assertMatchesRegularExpression('/Ember Rising.*?data-owned|data-owned.*?Ember Rising/s', $html);
    }

    public function test_someone_elses_bottle_does_not_badge_for_me(): void
    {
        $this->bottle(User::factory()->create(), $this->ember);

        $this->actingAs(User::factory()->create())->get('/catalog/phoenix-parlour')->assertDontSee('data-owned', false);
    }

    public function test_history_bottles_are_not_badged(): void
    {
        $me = User::factory()->create();
        $this->bottle($me, $this->ember, 'used_up');

        $this->actingAs($me)->get('/catalog/phoenix-parlour')->assertDontSee('data-owned', false);
    }

    public function test_a_guest_sees_no_badges(): void
    {
        $this->bottle(User::factory()->create(), $this->ember);

        $this->get('/catalog/phoenix-parlour')->assertOk()->assertDontSee('data-owned', false);
    }

    public function test_a_badge_never_leaks_through_the_shared_cache(): void
    {
        $me = User::factory()->create();
        $this->bottle($me, $this->ember);

        $this->actingAs($me)->get('/catalog/phoenix-parlour')->assertSee('data-owned', false);
        auth()->logout();
        $this->get('/catalog/phoenix-parlour')->assertDontSee('data-owned', false);
    }
}
