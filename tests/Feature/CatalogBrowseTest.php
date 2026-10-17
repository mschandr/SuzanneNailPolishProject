<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Polish;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogBrowseTest extends TestCase
{
    use RefreshDatabase;

    private function polish(Brand $brand, string $name, array $extra = []): Polish
    {
        return Polish::create(['brand_id' => $brand->id, 'name' => $name] + $extra);
    }

    public function test_anyone_can_see_the_list_of_brands(): void
    {
        Brand::create(['name' => 'Holo Taco']);
        Brand::create(['name' => 'ILNP']);

        $this->get('/catalog')->assertOk()->assertSeeInOrder(['Holo Taco', 'ILNP']);
    }

    public function test_a_brand_page_lists_only_its_polishes_alphabetically(): void
    {
        $taco = Brand::create(['name' => 'Holo Taco']);
        $ilnp = Brand::create(['name' => 'ILNP']);
        $this->polish($taco, 'Zesty');
        $this->polish($taco, 'Apple');
        $this->polish($ilnp, 'Other Brand Shade');

        $this->get('/catalog/holo-taco')
            ->assertOk()
            ->assertSeeInOrder(['Apple', 'Zesty'])
            ->assertDontSee('Other Brand Shade');
    }

    public function test_each_polish_links_to_its_canonical_page_and_shows_its_swatch(): void
    {
        $brand = Brand::create(['name' => 'Holo Taco']);
        $polish = $this->polish($brand, 'Big Apple Red', ['swatch_path' => 'swatches/red.jpg']);

        $this->get('/catalog/holo-taco')
            ->assertSee($polish->url(), false)
            ->assertSee('swatches/red.jpg', false);
    }

    public function test_a_brand_page_is_paginated_at_24(): void
    {
        $brand = Brand::create(['name' => 'Holo Taco']);
        foreach (range(1, 30) as $i) {
            $this->polish($brand, sprintf('Shade %02d', $i));
        }

        $this->get('/catalog/holo-taco')->assertSee('Shade 24')->assertDontSee('Shade 25');
        $this->get('/catalog/holo-taco?page=2')->assertSee('Shade 25')->assertSee('Shade 30');
    }

    public function test_an_unknown_brand_is_404(): void
    {
        $this->get('/catalog/nope')->assertNotFound();
    }

    public function test_slugs_stay_unique_when_names_slugify_the_same(): void
    {
        $a = Brand::create(['name' => 'O.P.I']);
        $b = Brand::create(['name' => 'OPI']);

        $this->assertNotSame($a->slug, $b->slug);
    }

    public function test_a_garbage_page_number_shows_page_one_and_never_poisons_page_two(): void
    {
        $brand = Brand::create(['name' => 'Holo Taco']);
        foreach (range(1, 30) as $i) {
            $this->polish($brand, sprintf('Shade %02d', $i));
        }

        $this->get('/catalog/holo-taco?page=2abc')->assertOk()->assertSee('Shade 01');
        $this->get('/catalog/holo-taco?page=2')->assertSee('Shade 25')->assertDontSee('Shade 01');
    }

    public function test_a_page_past_the_end_is_404(): void
    {
        $brand = Brand::create(['name' => 'Holo Taco']);
        $this->polish($brand, 'Only One');

        $this->get('/catalog/holo-taco?page=99')->assertNotFound();
    }

    public function test_an_empty_brand_still_shows_page_one(): void
    {
        Brand::create(['name' => 'Holo Taco']);

        $this->get('/catalog/holo-taco')->assertOk();
    }
}
