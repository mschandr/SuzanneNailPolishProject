<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\ColorFamily;
use App\Models\Finish;
use App\Models\Polish;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PolishPageTest extends TestCase
{
    use RefreshDatabase;

    private function polish(): Polish
    {
        $brand = Brand::create(['name' => 'Holo Taco']);
        $polish = Polish::create([
            'brand_id' => $brand->id,
            'name' => 'Big Apple Red',
            'collection' => 'Spring 2026',
            'color_family_id' => ColorFamily::create(['name' => 'Red'])->id,
            'vendor_url' => 'https://holotaco.example/products/big-apple-red',
            'swatch_path' => 'swatches/red.jpg',
        ]);
        $polish->finishes()->attach([
            Finish::create(['name' => 'Creme'])->id,
            Finish::create(['name' => 'Shimmer'])->id,
        ]);

        return $polish->fresh();
    }

    public function test_the_canonical_url_shows_the_polish_without_signing_in(): void
    {
        $polish = $this->polish();

        $this->get($polish->url())
            ->assertOk()
            ->assertSee('Big Apple Red')
            ->assertSee('Holo Taco')
            ->assertSee('Spring 2026')
            ->assertSee('Red')
            ->assertSeeInOrder(['Creme', 'Shimmer'])
            ->assertSee('swatches/red.jpg', false)
            ->assertSee('https://holotaco.example/products/big-apple-red', false);
    }

    public function test_the_url_has_the_shape_brand_uuid_color_name(): void
    {
        $polish = $this->polish();

        $this->assertSame("/holo-taco/{$polish->id}/big-apple-red", $polish->url());
    }

    public function test_a_wrong_color_slug_redirects_to_the_canonical_url(): void
    {
        $polish = $this->polish();

        $this->get("/holo-taco/{$polish->id}/old-name")->assertRedirect($polish->url())->assertStatus(301);
    }

    public function test_a_wrong_brand_slug_redirects_to_the_canonical_url(): void
    {
        $polish = $this->polish();

        $this->get("/some-old-brand/{$polish->id}/big-apple-red")->assertStatus(301)->assertRedirect($polish->url());
    }

    public function test_an_unknown_uuid_is_404(): void
    {
        $this->polish();

        $this->get('/holo-taco/0198aaaa-0000-7000-8000-000000000000/x')->assertNotFound();
    }

    public function test_a_non_uuid_id_is_404(): void
    {
        $this->get('/holo-taco/not-a-uuid/x')->assertNotFound();
    }

    public function test_the_page_notes_a_polish_with_no_vendor_link(): void
    {
        $polish = $this->polish();
        $polish->update(['vendor_url' => null]);

        $this->get($polish->url())->assertOk()->assertDontSee('Buy');
    }

    public function test_an_uppercase_uuid_redirects_to_the_canonical_lowercase_url(): void
    {
        $polish = $this->polish();

        $this->get('/holo-taco/'.strtoupper($polish->id).'/big-apple-red')
            ->assertStatus(301)->assertRedirect($polish->url());
    }

    public function test_only_an_admin_sees_the_edit_link(): void
    {
        $polish = $this->polish();

        $this->get($polish->url())->assertDontSee('/edit');
        $this->actingAs(\App\Models\User::factory()->create())->get($polish->url())->assertDontSee('/edit');
        $this->actingAs(\App\Models\User::factory()->admin()->create())->get($polish->url())
            ->assertSee("/admin/polishes/{$polish->id}/edit", false);
    }
}
