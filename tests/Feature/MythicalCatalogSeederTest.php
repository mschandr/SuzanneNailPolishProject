<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Polish;
use Database\Seeders\MythicalCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MythicalCatalogSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_three_brands_and_ten_complete_polishes(): void
    {
        Storage::fake('public');

        $this->seed(MythicalCatalogSeeder::class);

        $this->assertSame(3, Brand::count());
        $this->assertSame(10, Polish::count());
        $this->assertSame(0, Polish::whereNull('color_family_id')->count());
        $this->assertSame(0, Polish::doesntHave('finishes')->count());
        foreach (Polish::all() as $polish) {
            Storage::disk('public')->assertExists($polish->swatch_path);
        }
    }

    public function test_running_it_twice_adds_nothing(): void
    {
        Storage::fake('public');

        $this->seed(MythicalCatalogSeeder::class);
        $this->seed(MythicalCatalogSeeder::class);

        $this->assertSame(3, Brand::count());
        $this->assertSame(10, Polish::count());
    }

    public function test_the_catalog_pages_work_with_the_seeded_data(): void
    {
        Storage::fake('public');
        $this->seed(MythicalCatalogSeeder::class);

        $this->get('/catalog')->assertOk()->assertSee('Phoenix Parlour');
        $this->get(Polish::where('name', 'Ember Rising')->firstOrFail()->url())->assertOk()->assertSee('Ember Rising');
    }
}
