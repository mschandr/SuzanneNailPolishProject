<?php

namespace Tests\Feature;

use App\Models\Bottle;
use App\Models\Brand;
use App\Models\Polish;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogSearchTest extends TestCase
{
    use RefreshDatabase;

    private Brand $taco;

    protected function setUp(): void
    {
        parent::setUp();

        $this->taco = Brand::create(['name' => 'Holo Taco']);
        Polish::create(['brand_id' => $this->taco->id, 'name' => 'Cherry Bomb']);
        Polish::create(['brand_id' => $this->taco->id, 'name' => 'Lemon Drop']);
        Polish::create(['brand_id' => Brand::create(['name' => 'ILNP'])->id, 'name' => 'Cherry Blossom']);
    }

    public function test_anyone_can_search_every_polish_by_shade(): void
    {
        $this->get('/catalog?q=cherry')->assertOk()
            ->assertSee('Cherry Bomb')->assertSee('Cherry Blossom')->assertDontSee('Lemon Drop');
    }

    public function test_the_search_also_matches_the_brand_ignoring_case(): void
    {
        $this->get('/catalog?q=HOLO')->assertSee('Cherry Bomb')->assertSee('Lemon Drop')->assertDontSee('Cherry Blossom');
    }

    public function test_it_never_finds_a_private_polish(): void
    {
        $me = User::factory()->create();
        $secret = new Polish(['brand_id' => $this->taco->id, 'name' => 'Secret Cherry']);
        $secret->user_id = $me->id;
        $secret->save();

        $this->actingAs($me)->get('/catalog?q=secret')->assertOk()->assertDontSee('Secret Cherry');
        $this->get('/catalog?q=secret')->assertDontSee('Secret Cherry');
    }

    public function test_wildcards_are_searched_literally(): void
    {
        $this->get('/catalog?q=%25')->assertOk()->assertDontSee('Cherry Bomb')->assertSee('Nothing matches');
        $this->get('/catalog?q=_')->assertOk()->assertDontSee('Cherry Bomb');
    }

    public function test_results_are_limited_and_say_so(): void
    {
        foreach (range(1, 50) as $i) {
            Polish::create(['brand_id' => $this->taco->id, 'name' => sprintf('Many %02d', $i)]);
        }

        $this->get('/catalog?q=many')->assertSee('Many 48')->assertDontSee('Many 49')->assertSee('Showing the first 48');
    }

    public function test_an_owned_polish_is_badged_for_its_owner_only(): void
    {
        $me = User::factory()->create();
        $other = User::factory()->create();
        $me->bottles()->create(['polish_id' => Polish::where('name', 'Cherry Bomb')->value('id'), 'status' => Bottle::OWNED]);

        $this->actingAs($me)->get('/catalog?q=cherry')->assertSee('data-owned', false);
        $this->actingAs($other)->get('/catalog?q=cherry')->assertDontSee('data-owned', false);
        $this->get('/catalog?q=cherry')->assertDontSee('data-owned', false);
    }

    public function test_a_blank_search_shows_the_brand_list(): void
    {
        $this->get('/catalog?q=%20')->assertOk()->assertSee('Holo Taco')->assertDontSee('Search the Catalog</h1>', false);
    }
}
