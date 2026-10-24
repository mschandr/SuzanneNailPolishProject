<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Polish;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPolishSearchTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $taco = Brand::create(['name' => 'Holo Taco']);
        Polish::create(['brand_id' => $taco->id, 'name' => 'Cherry Bomb']);
        Polish::create(['brand_id' => $taco->id, 'name' => 'Lemon Drop']);
    }

    public function test_the_admin_lists_every_polish_with_an_edit_link(): void
    {
        $polish = Polish::where('name', 'Cherry Bomb')->first();

        $this->actingAs($this->admin)->get('/admin/polishes')->assertOk()
            ->assertSee('Cherry Bomb')->assertSee('Lemon Drop')
            ->assertSee("/admin/polishes/{$polish->id}/edit", false);
    }

    public function test_it_searches_by_shade_or_brand(): void
    {
        $this->actingAs($this->admin)->get('/admin/polishes?q=cherry')->assertSee('Cherry Bomb')->assertDontSee('Lemon Drop');
        $this->actingAs($this->admin)->get('/admin/polishes?q=taco')->assertSee('Cherry Bomb')->assertSee('Lemon Drop');
    }

    public function test_wildcards_are_literal(): void
    {
        $this->actingAs($this->admin)->get('/admin/polishes?q=%25')->assertOk()->assertDontSee('Cherry Bomb')->assertSee('Nothing matches');
    }

    public function test_private_polishes_are_never_listed(): void
    {
        $owner = User::factory()->create();
        $secret = new Polish(['brand_id' => Brand::first()->id, 'name' => 'Secret Shade']);
        $secret->user_id = $owner->id;
        $secret->save();

        $this->actingAs($this->admin)->get('/admin/polishes?q=secret')->assertOk()->assertDontSee('Secret Shade');
    }

    public function test_the_dashboard_links_to_it(): void
    {
        $this->actingAs($this->admin)->get('/admin')->assertSee('href="/admin/polishes"', false)->assertSee('Find and edit a polish');
    }
}
