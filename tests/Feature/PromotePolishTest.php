<?php

namespace Tests\Feature;

use App\Models\Bottle;
use App\Models\Brand;
use App\Models\ColorFamily;
use App\Models\Polish;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromotePolishTest extends TestCase
{
    use RefreshDatabase;

    private User $suzanne;

    private User $admin;

    private Polish $secret;

    protected function setUp(): void
    {
        parent::setUp();
        $this->suzanne = User::factory()->create(['display_name' => 'Suzanne']);
        $this->admin = User::factory()->admin()->create();
        $this->secret = $this->privatePolish($this->suzanne, 'Indie Co', 'Discontinued Gem');
    }

    private function privatePolish(User $owner, string $brand, string $name): Polish
    {
        $brandModel = Brand::firstOrCreate(['name_key' => strtolower($brand)], ['name' => $brand]);
        $polish = new Polish(['brand_id' => $brandModel->id, 'name' => $name]);
        $polish->user_id = $owner->id;
        $polish->save();

        return $polish;
    }

    public function test_the_owner_can_suggest_it_and_withdraw_the_suggestion(): void
    {
        $this->actingAs($this->suzanne)->post("/my-polishes/{$this->secret->id}/suggest")->assertRedirect();
        $this->assertNotNull($this->secret->fresh()->promotion_requested_at);

        $this->actingAs($this->suzanne)->delete("/my-polishes/{$this->secret->id}/suggest")->assertRedirect();
        $this->assertNull($this->secret->fresh()->promotion_requested_at);
    }

    public function test_nobody_else_can_suggest_it(): void
    {
        $this->actingAs(User::factory()->create())->post("/my-polishes/{$this->secret->id}/suggest")->assertNotFound();

        $this->assertNull($this->secret->fresh()->promotion_requested_at);
    }

    public function test_the_admin_sees_only_suggested_polishes_and_who_suggested_them(): void
    {
        $this->privatePolish($this->suzanne, 'Indie Co', 'Never Suggested');
        $this->actingAs($this->suzanne)->post("/my-polishes/{$this->secret->id}/suggest");

        $this->actingAs($this->admin)->get('/admin/suggestions')
            ->assertOk()
            ->assertSee('Discontinued Gem')
            ->assertSee('Suzanne')
            ->assertDontSee('Never Suggested');
    }

    public function test_promoting_makes_it_public_and_drops_the_link_to_its_creator(): void
    {
        $this->actingAs($this->suzanne)->post("/my-polishes/{$this->secret->id}/suggest");

        $this->actingAs($this->admin)->post("/admin/suggestions/{$this->secret->id}/promote")->assertRedirect('/admin/suggestions');

        $fresh = $this->secret->fresh();
        $this->assertNull($fresh->user_id);
        $this->assertNull($fresh->promotion_requested_at);
        $this->assertSame($this->secret->id, $fresh->id);
    }

    public function test_once_promoted_it_is_visible_to_everyone_immediately_despite_the_cache(): void
    {
        $this->actingAs($this->suzanne)->post("/my-polishes/{$this->secret->id}/suggest");
        auth()->logout();
        $url = $this->secret->load('brand')->url();
        // Warm the shared caches as a guest while it is still private.
        $this->get($url)->assertNotFound();
        $this->get('/catalog')->assertDontSee('Indie Co');

        $this->actingAs($this->admin)->post("/admin/suggestions/{$this->secret->id}/promote");
        auth()->logout();

        $this->get($url)->assertOk()->assertSee('Discontinued Gem');
        $this->get('/catalog')->assertSee('Indie Co');
        $this->get('/catalog/indie-co')->assertSee('Discontinued Gem');
    }

    public function test_the_creators_bottles_survive_promotion(): void
    {
        $bottle = new Bottle(['polish_id' => $this->secret->id]);
        $bottle->user_id = $this->suzanne->id;
        $bottle->save();
        $this->actingAs($this->suzanne)->post("/my-polishes/{$this->secret->id}/suggest");

        $this->actingAs($this->admin)->post("/admin/suggestions/{$this->secret->id}/promote");

        $this->assertSame($this->secret->id, $bottle->fresh()->polish_id);
    }

    public function test_promotion_is_refused_if_the_public_catalog_already_has_that_shade(): void
    {
        $this->actingAs($this->suzanne)->post("/my-polishes/{$this->secret->id}/suggest");
        Polish::create(['brand_id' => $this->secret->brand_id, 'name' => 'DISCONTINUED gem']);

        $this->actingAs($this->admin)->post("/admin/suggestions/{$this->secret->id}/promote")
            ->assertSessionHasErrors('promote');

        $this->assertTrue($this->secret->fresh()->isPrivate());
    }

    public function test_only_suggested_polishes_can_be_promoted(): void
    {
        $this->actingAs($this->admin)->post("/admin/suggestions/{$this->secret->id}/promote")->assertNotFound();

        $this->assertTrue($this->secret->fresh()->isPrivate());
    }

    public function test_declining_clears_the_suggestion_and_keeps_it_private(): void
    {
        $this->actingAs($this->suzanne)->post("/my-polishes/{$this->secret->id}/suggest");

        $this->actingAs($this->admin)->delete("/admin/suggestions/{$this->secret->id}")->assertRedirect('/admin/suggestions');

        $fresh = $this->secret->fresh();
        $this->assertTrue($fresh->isPrivate());
        $this->assertNull($fresh->promotion_requested_at);
    }

    public function test_the_admin_polish_form_cannot_edit_a_private_polish(): void
    {
        $this->actingAs($this->admin)->get("/admin/polishes/{$this->secret->id}/edit")->assertNotFound();
        $this->actingAs($this->admin)->put("/admin/polishes/{$this->secret->id}", [
            'brand' => 'Indie Co', 'name' => 'Hijacked', 'color_family_id' => ColorFamily::create(['name' => 'Red'])->id,
        ])->assertNotFound();

        $this->assertSame('Discontinued Gem', $this->secret->fresh()->name);
    }

    public function test_the_admin_can_create_a_public_polish_with_a_name_a_user_holds_privately(): void
    {
        $this->actingAs($this->admin)->post('/admin/polishes', [
            'brand' => 'Indie Co', 'name' => 'Discontinued Gem', 'color_family_id' => ColorFamily::create(['name' => 'Red'])->id,
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, Polish::public()->count());
    }

    public function test_editing_a_suggested_polish_withdraws_the_suggestion_so_the_admin_never_promotes_unreviewed_changes(): void
    {
        $this->actingAs($this->suzanne)->post("/my-polishes/{$this->secret->id}/suggest");

        $this->actingAs($this->suzanne)->put("/my-polishes/{$this->secret->id}", ['brand' => 'Indie Co', 'name' => 'Changed After Review']);

        $this->assertNull($this->secret->fresh()->promotion_requested_at);
        $this->actingAs($this->admin)->get('/admin/suggestions')->assertDontSee('Changed After Review');
        $this->actingAs($this->admin)->post("/admin/suggestions/{$this->secret->id}/promote")->assertNotFound();
    }
}
