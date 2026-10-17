<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\ColorFamily;
use App\Models\Finish;
use App\Models\Polish;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CatalogCacheTest extends TestCase
{
    use RefreshDatabase;

    private function polish(): Polish
    {
        $brand = Brand::create(['name' => 'Holo Taco']);

        return Polish::create(['brand_id' => $brand->id, 'name' => 'Big Apple Red']);
    }

    /** Number of queries that read the polishes table while serving $url. */
    private function polishQueries(string $url): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get($url)->assertOk();
        DB::disableQueryLog();

        return collect(DB::getQueryLog())->filter(fn ($q) => str_contains($q['query'], '"polishes"'))->count();
    }

    public function test_a_repeat_view_of_a_brand_page_does_not_query_polishes(): void
    {
        $this->polish();

        $this->assertGreaterThan(0, $this->polishQueries('/catalog/holo-taco'));
        $this->assertSame(0, $this->polishQueries('/catalog/holo-taco'));
    }

    public function test_a_repeat_view_of_a_polish_page_does_not_query_polishes(): void
    {
        $polish = $this->polish();

        $this->assertGreaterThan(0, $this->polishQueries($polish->url()));
        $this->assertSame(0, $this->polishQueries($polish->url()));
    }

    public function test_an_admin_edit_shows_on_the_very_next_view(): void
    {
        $polish = $this->polish();
        $this->get('/catalog/holo-taco')->assertSee('Big Apple Red');
        $this->get($polish->url())->assertOk();

        $this->actingAs(User::factory()->admin()->create())->put("/admin/polishes/{$polish->id}", [
            'brand' => 'Holo Taco',
            'name' => 'Big Apple Crimson',
            'color_family_id' => ColorFamily::create(['name' => 'Red'])->id,
        ]);
        auth()->logout();

        $this->get('/catalog/holo-taco')->assertSee('Big Apple Crimson')->assertDontSee('Big Apple Red');
        $this->get($polish->fresh('brand')->url())->assertOk()->assertSee('Big Apple Crimson');
    }

    public function test_renaming_a_finish_shows_on_the_polish_page(): void
    {
        $polish = $this->polish();
        $finish = Finish::create(['name' => 'Creme']);
        $polish->finishes()->attach($finish->id);
        $this->get($polish->url())->assertSee('Creme');

        $this->actingAs(User::factory()->admin()->create())
            ->put("/admin/finishes/{$finish->id}", ['name' => 'Cream']);
        auth()->logout();

        $this->get($polish->url())->assertSee('Cream')->assertDontSee('Creme');
    }

    public function test_a_stale_slug_still_redirects_after_caching(): void
    {
        $polish = $this->polish();
        $this->get($polish->url())->assertOk();

        $this->get("/holo-taco/{$polish->id}/stale")->assertStatus(301)->assertRedirect($polish->url());
    }

    public function test_flushing_the_cache_does_not_resurrect_stale_entries(): void
    {
        $polish = $this->polish();
        $this->get($polish->url())->assertSee('Big Apple Red');

        // Change the row behind the cache's back, then wipe only the version key
        // (what an eviction or cache:clear does).
        Polish::whereKey($polish->id)->update(['name' => 'Renamed']);
        \Illuminate\Support\Facades\Cache::forget('catalog:version');

        $this->get("/holo-taco/{$polish->id}/renamed")->assertOk()->assertSee('Renamed');
    }
}
