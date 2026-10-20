<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Polish;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class PrivatePolishVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Polish $secret;

    private Polish $public;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create();
        $brand = Brand::create(['name' => 'Phoenix Parlour']);
        $this->public = Polish::create(['brand_id' => $brand->id, 'name' => 'Ember Rising']);
        $this->secret = $this->privatePolish($this->owner, $brand, 'Suzannes Secret Shade');
    }

    private function privatePolish(User $user, Brand $brand, string $name): Polish
    {
        $polish = new Polish(['brand_id' => $brand->id, 'name' => $name]);
        $polish->user_id = $user->id;
        $polish->save();

        return $polish->load('brand');
    }

    public function test_it_is_not_on_the_public_brand_page(): void
    {
        $this->get('/catalog/phoenix-parlour')->assertOk()->assertSee('Ember Rising')->assertDontSee('Suzannes Secret Shade');
        $this->actingAs($this->owner)->get('/catalog/phoenix-parlour')->assertDontSee('Suzannes Secret Shade');
    }

    public function test_pagination_counts_only_public_polishes(): void
    {
        $brand = $this->public->brand;
        foreach (range(1, 24) as $i) {
            $this->privatePolish($this->owner, $brand, sprintf('Hidden %02d', $i));
        }

        // 1 public polish + 24 private: a second page would mean private ones are being counted.
        $this->get('/catalog/phoenix-parlour?page=2')->assertNotFound();
    }

    public function test_a_brand_with_only_private_polishes_is_not_listed_or_reachable(): void
    {
        $indie = Brand::create(['name' => 'Secret Indie Co']);
        $this->privatePolish($this->owner, $indie, 'Hidden Gem');

        $this->get('/catalog')->assertOk()->assertSee('Phoenix Parlour')->assertDontSee('Secret Indie Co');
        $this->get('/catalog/secret-indie-co')->assertNotFound();
        $this->actingAs($this->owner)->get('/catalog/secret-indie-co')->assertNotFound();
    }

    public function test_strangers_and_guests_get_404_on_the_direct_url(): void
    {
        $this->get($this->secret->url())->assertNotFound();
        $this->actingAs(User::factory()->create())->get($this->secret->url())->assertNotFound();
    }

    public function test_the_404_is_the_same_as_for_a_polish_that_does_not_exist(): void
    {
        $missing = $this->get('/phoenix-parlour/0198aaaa-0000-7000-8000-000000000000/x');
        $secret = $this->get($this->secret->url());

        $this->assertSame($missing->getStatusCode(), $secret->getStatusCode());
        $secret->assertDontSee('Suzannes Secret Shade');
    }

    public function test_a_wrong_slug_does_not_redirect_a_stranger_to_the_real_url(): void
    {
        $this->get("/phoenix-parlour/{$this->secret->id}/wrong-slug")->assertNotFound();
    }

    public function test_the_owner_can_open_it(): void
    {
        $this->actingAs($this->owner)->get($this->secret->url())->assertOk()->assertSee('Suzannes Secret Shade');
    }

    public function test_the_admin_cannot_open_it_unless_the_owner_suggested_it(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get($this->secret->url())->assertNotFound();

        $this->secret->forceFill(['promotion_requested_at' => now()])->save();
        $this->actingAs($admin)->get($this->secret->url())->assertOk();
    }

    public function test_an_owners_edit_shows_immediately_and_a_deletion_is_a_404(): void
    {
        $this->actingAs($this->owner)->get($this->secret->url())->assertOk()->assertSee('Suzannes Secret Shade');

        $this->actingAs($this->owner)->put("/my-polishes/{$this->secret->id}", ['brand' => 'Phoenix Parlour', 'name' => 'Renamed Secret'])
            ->assertRedirect();
        $renamed = $this->secret->fresh('brand')->url();

        $this->actingAs($this->owner)->get($renamed)->assertOk()->assertSee('Renamed Secret')->assertDontSee('Suzannes Secret Shade');

        $this->actingAs($this->owner)->delete("/my-polishes/{$this->secret->id}");
        $this->actingAs($this->owner)->get($renamed)->assertNotFound();
    }

    public function test_private_polishes_are_never_written_to_the_shared_cache(): void
    {
        $this->actingAs($this->owner)->get($this->secret->url())->assertOk();

        $version = Cache::get('catalog:version');
        $this->assertFalse(Cache::has("catalog:v{$version}:polish:{$this->secret->id}"));
    }

    public function test_a_private_only_brand_is_not_in_the_admin_forms_brand_suggestions(): void
    {
        $indie = Brand::create(['name' => 'Secret Indie Co']);
        $this->privatePolish($this->owner, $indie, 'Hidden Gem');

        $this->actingAs(User::factory()->admin()->create())->get('/admin/polishes/create')
            ->assertOk()->assertSee('Phoenix Parlour')->assertDontSee('Secret Indie Co');
    }

    public function test_a_new_brand_created_for_a_private_polish_does_not_invalidate_the_public_cache(): void
    {
        $this->get('/catalog')->assertOk();
        $before = Cache::get('catalog:version');

        $this->actingAs($this->owner)->post('/my-polishes', ['brand' => 'Brand New Indie', 'name' => 'First Shade'])->assertRedirect();

        $this->assertSame($before, Cache::get('catalog:version'));
    }

    public function test_the_shared_cache_never_serves_it_to_a_stranger(): void
    {
        $this->actingAs($this->owner)->get($this->secret->url())->assertOk();
        auth()->logout();

        $this->get($this->secret->url())->assertNotFound();
        $this->actingAs(User::factory()->create())->get($this->secret->url())->assertNotFound();
    }

    public function test_adding_a_private_polish_does_not_invalidate_the_public_cache(): void
    {
        $this->get('/catalog/phoenix-parlour')->assertOk();
        $before = Cache::get('catalog:version');

        $this->privatePolish($this->owner, $this->public->brand, 'Another Secret');

        $this->assertSame($before, Cache::get('catalog:version'));
    }

    public function test_the_owners_own_bottle_of_it_can_be_added_but_a_strangers_cannot(): void
    {
        $this->actingAs($this->owner)->post("/polishes/{$this->secret->id}/bottles")->assertRedirect();
        $this->assertSame(1, $this->owner->bottles()->count());

        $stranger = User::factory()->create();
        $this->actingAs($stranger)->post("/polishes/{$this->secret->id}/bottles")->assertNotFound();
        $this->assertSame(0, $stranger->bottles()->count());
    }
}
