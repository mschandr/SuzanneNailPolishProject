<?php

namespace Tests\Feature;

use App\Models\Bottle;
use App\Models\Brand;
use App\Models\Manicure;
use App\Models\ManicurePolish;
use App\Models\Polish;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\Support\TestImages;
use Tests\TestCase;

class ManicureLayersTest extends TestCase
{
    use RefreshDatabase;

    private User $me;

    private Manicure $manicure;

    private Brand $brand;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('photos');
        $this->me = User::factory()->create(['display_name' => 'Suzanne']);
        $this->manicure = $this->makeManicure($this->me);
        $this->brand = Brand::create(['name' => 'Phoenix Parlour']);
    }

    private function makeManicure(User $owner, ?string $visibility = null): Manicure
    {
        $response = $this->actingAs($owner)->post('/manicures', [
            'worn_on' => '2026-10-15', 'visibility' => $visibility,
            'photos' => [TestImages::upload('a.jpg', 400, 300)],
        ]);
        auth()->logout();

        return Manicure::findOrFail(basename($response->headers->get('Location')));
    }

    private function polish(string $name, ?User $private = null, ?Brand $brand = null): Polish
    {
        $polish = new Polish(['brand_id' => ($brand ?? $this->brand)->id, 'name' => $name]);
        $polish->user_id = $private?->id;
        $polish->save();

        return $polish;
    }

    private function bottle(User $user, Polish $polish, string $status = 'owned'): Bottle
    {
        $bottle = new Bottle(['polish_id' => $polish->id]);
        $bottle->user_id = $user->id;
        $bottle->status = $status;
        $bottle->save();

        return $bottle;
    }

    private function add(Polish $polish, array $extra = [], ?User $as = null, ?Manicure $to = null)
    {
        $to ??= $this->manicure;

        return $this->actingAs($as ?? $this->me)->post("/manicures/{$to->id}/polishes", ['polish_id' => $polish->id] + $extra);
    }

    private function names(?Manicure $m = null): array
    {
        return ($m ?? $this->manicure)->layers()->with('polish')->get()->map(fn ($l) => $l->polish->name)->all();
    }

    public function test_polishes_are_added_in_order(): void
    {
        $base = $this->polish('Base Coat');
        $color = $this->polish('Ember Rising');
        $top = $this->polish('Top Coat');

        $this->add($base)->assertRedirect("/manicures/{$this->manicure->id}/polishes");
        $this->add($color);
        $this->add($top);

        $this->assertSame(['Base Coat', 'Ember Rising', 'Top Coat'], $this->names());
        $this->assertSame([0, 1, 2], $this->manicure->layers()->pluck('position')->all());
    }

    public function test_the_same_polish_can_be_listed_twice_for_two_coats(): void
    {
        $color = $this->polish('Ember Rising');

        $this->add($color);
        $this->add($color);

        $this->assertSame(['Ember Rising', 'Ember Rising'], $this->names());
    }

    public function test_a_polish_the_user_does_not_own_can_be_listed(): void
    {
        $someoneElses = $this->polish('Friends Polish');

        $this->add($someoneElses)->assertSessionHasNoErrors();

        $this->assertSame(['Friends Polish'], $this->names());
    }

    public function test_my_own_private_polish_can_be_listed(): void
    {
        $mine = $this->polish('My Secret', $this->me);

        $this->add($mine)->assertSessionHasNoErrors();

        $this->assertSame(['My Secret'], $this->names());
    }

    public function test_someone_elses_private_polish_cannot_be_listed(): void
    {
        $theirs = $this->polish('Their Secret', User::factory()->create());

        $this->add($theirs)->assertSessionHasErrors('polish_id');

        $this->assertSame([], $this->names());
    }

    public function test_an_unknown_polish_is_refused(): void
    {
        $this->actingAs($this->me)->post("/manicures/{$this->manicure->id}/polishes", ['polish_id' => '0198aaaa-0000-7000-8000-000000000000'])
            ->assertSessionHasErrors('polish_id');
        $this->actingAs($this->me)->post("/manicures/{$this->manicure->id}/polishes", [])->assertSessionHasErrors('polish_id');
    }

    public function test_one_of_my_own_bottles_of_that_polish_can_be_linked(): void
    {
        $color = $this->polish('Ember Rising');
        $bottle = $this->bottle($this->me, $color);

        $this->add($color, ['bottle_id' => $bottle->id])->assertSessionHasNoErrors();

        $this->assertSame($bottle->id, $this->manicure->layers()->firstOrFail()->bottle_id);
    }

    public function test_a_bottle_of_a_different_polish_cannot_be_linked(): void
    {
        $color = $this->polish('Ember Rising');
        $other = $this->polish('Golden Plume');
        $bottle = $this->bottle($this->me, $other);

        $this->add($color, ['bottle_id' => $bottle->id])->assertSessionHasErrors('bottle_id');

        $this->assertSame(0, $this->manicure->layers()->count());
    }

    public function test_someone_elses_bottle_cannot_be_linked(): void
    {
        $color = $this->polish('Ember Rising');
        $theirs = $this->bottle(User::factory()->create(), $color);

        $this->add($color, ['bottle_id' => $theirs->id])->assertSessionHasErrors('bottle_id');
    }

    public function test_a_used_up_bottle_can_still_be_linked_to_a_past_manicure(): void
    {
        $color = $this->polish('Ember Rising');
        $bottle = $this->bottle($this->me, $color, 'used_up');

        $this->add($color, ['bottle_id' => $bottle->id])->assertSessionHasNoErrors();
    }

    public function test_at_most_ten_layers(): void
    {
        $color = $this->polish('Ember Rising');
        foreach (range(1, 10) as $_) {
            $this->add($color);
        }

        $this->add($color)->assertSessionHasErrors('polish_id');

        $this->assertSame(10, $this->manicure->layers()->count());
    }

    public function test_only_the_owner_can_change_the_list(): void
    {
        $color = $this->polish('Ember Rising');
        $stranger = User::factory()->create();

        $this->add($color, [], $stranger)->assertNotFound();
        $this->actingAs($stranger)->get("/manicures/{$this->manicure->id}/polishes")->assertNotFound();

        $this->assertSame(0, $this->manicure->layers()->count());
    }

    public function test_layers_can_be_moved_up_and_down(): void
    {
        foreach (['A', 'B', 'C'] as $n) {
            $this->add($this->polish($n));
        }
        [$a, $b, $c] = $this->manicure->layers()->get()->all();

        $this->actingAs($this->me)->post("/manicure-polishes/{$c->id}/move", ['direction' => 'up'])->assertRedirect();
        $this->assertSame(['A', 'C', 'B'], $this->names());

        $this->actingAs($this->me)->post("/manicure-polishes/{$a->id}/move", ['direction' => 'down'])->assertRedirect();
        $this->assertSame(['C', 'A', 'B'], $this->names());
        $this->assertSame([0, 1, 2], $this->manicure->layers()->pluck('position')->all());
    }

    public function test_moving_past_the_ends_changes_nothing(): void
    {
        foreach (['A', 'B'] as $n) {
            $this->add($this->polish($n));
        }
        [$a, $b] = $this->manicure->layers()->get()->all();

        $this->actingAs($this->me)->post("/manicure-polishes/{$a->id}/move", ['direction' => 'up'])->assertRedirect();
        $this->actingAs($this->me)->post("/manicure-polishes/{$b->id}/move", ['direction' => 'down'])->assertRedirect();

        $this->assertSame(['A', 'B'], $this->names());
    }

    public function test_an_unknown_direction_is_refused(): void
    {
        $this->add($this->polish('A'));
        $layer = $this->manicure->layers()->firstOrFail();

        $this->actingAs($this->me)->post("/manicure-polishes/{$layer->id}/move", ['direction' => 'sideways'])
            ->assertSessionHasErrors('direction');
    }

    public function test_removing_a_layer_keeps_the_positions_contiguous(): void
    {
        foreach (['A', 'B', 'C'] as $n) {
            $this->add($this->polish($n));
        }
        $b = $this->manicure->layers()->get()[1];

        $this->actingAs($this->me)->delete("/manicure-polishes/{$b->id}")->assertRedirect("/manicures/{$this->manicure->id}/polishes");

        $this->assertSame(['A', 'C'], $this->names());
        $this->assertSame([0, 1], $this->manicure->layers()->pluck('position')->all());
    }

    public function test_a_polish_can_be_added_again_after_removals(): void
    {
        foreach (['A', 'B'] as $n) {
            $this->add($this->polish($n));
        }
        $first = $this->manicure->layers()->firstOrFail();
        $this->actingAs($this->me)->delete("/manicure-polishes/{$first->id}");

        $this->add($this->polish('C'))->assertSessionHasNoErrors();

        $this->assertSame(['B', 'C'], $this->names());
    }

    public function test_the_bottle_link_can_be_set_and_cleared(): void
    {
        $color = $this->polish('Ember Rising');
        $bottle = $this->bottle($this->me, $color);
        $this->add($color);
        $layer = $this->manicure->layers()->firstOrFail();

        $this->actingAs($this->me)->put("/manicure-polishes/{$layer->id}", ['bottle_id' => $bottle->id])->assertRedirect();
        $this->assertSame($bottle->id, $layer->fresh()->bottle_id);

        $this->actingAs($this->me)->put("/manicure-polishes/{$layer->id}", ['bottle_id' => ''])->assertRedirect();
        $this->assertNull($layer->fresh()->bottle_id);
    }

    public function test_relinking_to_a_bottle_of_another_polish_is_refused(): void
    {
        $color = $this->polish('Ember Rising');
        $wrong = $this->bottle($this->me, $this->polish('Golden Plume'));
        $this->add($color);
        $layer = $this->manicure->layers()->firstOrFail();

        $this->actingAs($this->me)->put("/manicure-polishes/{$layer->id}", ['bottle_id' => $wrong->id])
            ->assertSessionHasErrors('bottle_id');
    }

    public function test_someone_elses_layer_is_404_to_change(): void
    {
        $this->add($this->polish('A'));
        $layer = $this->manicure->layers()->firstOrFail();
        $stranger = User::factory()->create();

        $this->actingAs($stranger)->post("/manicure-polishes/{$layer->id}/move", ['direction' => 'down'])->assertNotFound();
        $this->actingAs($stranger)->put("/manicure-polishes/{$layer->id}", ['bottle_id' => ''])->assertNotFound();
        $this->actingAs($stranger)->delete("/manicure-polishes/{$layer->id}")->assertNotFound();

        $this->assertSame(1, $this->manicure->layers()->count());
    }

    public function test_deleting_the_bottle_keeps_the_layer_but_unlinks_it(): void
    {
        $color = $this->polish('Ember Rising');
        $bottle = $this->bottle($this->me, $color);
        $this->add($color, ['bottle_id' => $bottle->id]);

        $this->actingAs($this->me)->delete("/bottles/{$bottle->id}");

        $layer = $this->manicure->layers()->firstOrFail();
        $this->assertNull($layer->bottle_id);
        $this->assertSame(['Ember Rising'], $this->names());
    }

    public function test_deleting_a_private_polish_removes_it_from_the_list(): void
    {
        $mine = $this->polish('My Secret', $this->me);
        $keep = $this->polish('Keep Me');
        $this->add($keep);
        $this->add($mine);

        $this->actingAs($this->me)->delete("/my-polishes/{$mine->id}");

        $this->assertSame(['Keep Me'], $this->names());
    }

    public function test_deleting_the_manicure_removes_its_layers(): void
    {
        $this->add($this->polish('A'));

        $this->actingAs($this->me)->delete("/manicures/{$this->manicure->id}");

        $this->assertSame(0, ManicurePolish::count());
    }

    // --- search -------------------------------------------------------------

    public function test_search_finds_catalog_polishes_by_shade_or_brand_ignoring_case(): void
    {
        $this->polish('Ember Rising');
        $this->polish('Golden Plume');

        $page = $this->actingAs($this->me)->get("/manicures/{$this->manicure->id}/polishes?q=EMBER")->assertOk();
        $page->assertSee('Ember Rising')->assertDontSee('Golden Plume');

        $this->actingAs($this->me)->get("/manicures/{$this->manicure->id}/polishes?q=phoenix")
            ->assertSee('Ember Rising')->assertSee('Golden Plume');
    }

    public function test_search_includes_my_private_polishes_but_never_anyone_elses(): void
    {
        $this->polish('Mine Secret', $this->me);
        $this->polish('Theirs Secret', User::factory()->create());

        $this->actingAs($this->me)->get("/manicures/{$this->manicure->id}/polishes?q=secret")
            ->assertSee('Mine Secret')->assertDontSee('Theirs Secret');
    }

    public function test_search_treats_wildcards_literally_and_survives_junk(): void
    {
        $this->polish('Ember Rising');

        $this->actingAs($this->me)->get("/manicures/{$this->manicure->id}/polishes?q=%25")->assertOk()->assertDontSee('Ember Rising');
        $this->actingAs($this->me)->get("/manicures/{$this->manicure->id}/polishes?q=_")->assertOk()->assertDontSee('Ember Rising');
        $this->actingAs($this->me)->get("/manicures/{$this->manicure->id}/polishes?q[]=x")->assertOk();
    }

    public function test_search_results_are_limited_and_say_so(): void
    {
        foreach (range(1, 25) as $i) {
            $this->polish(sprintf('Shade %02d', $i));
        }

        $page = $this->actingAs($this->me)->get("/manicures/{$this->manicure->id}/polishes?q=shade")->assertOk();

        $page->assertSee('Shade 20')->assertDontSee('Shade 21')->assertSee('Showing the first 20');
    }

    public function test_search_offers_my_own_bottles_of_each_result_for_linking(): void
    {
        $color = $this->polish('Ember Rising');
        $bottle = $this->bottle($this->me, $color);
        $this->bottle(User::factory()->create(), $color);

        $html = $this->actingAs($this->me)->get("/manicures/{$this->manicure->id}/polishes?q=ember")->getContent();

        $this->assertStringContainsString($bottle->id, $html);
        $this->assertSame(1, substr_count($html, 'name="bottle_id"'), 'one picker, for the one result');
    }

    public function test_the_page_warns_that_private_polishes_show_their_names_on_public_manicures(): void
    {
        $this->actingAs($this->me)->get("/manicures/{$this->manicure->id}/polishes")
            ->assertSee('private polishes', false)->assertSee('name will be visible', false);
    }

    // --- display ------------------------------------------------------------

    public function test_the_manicure_page_lists_the_layers_in_order_with_links(): void
    {
        $a = $this->polish('Base Coat');
        $b = $this->polish('Ember Rising');
        $this->add($a);
        $this->add($b);
        $this->manicure->forceFill(['visibility' => 'public'])->save();
        auth()->logout();

        $this->get("/manicures/{$this->manicure->id}")->assertOk()
            ->assertSeeInOrder(['Base Coat', 'Ember Rising'])
            ->assertSee($b->fresh('brand')->url(), false);
    }

    public function test_strangers_do_not_see_which_bottles_were_linked(): void
    {
        $color = $this->polish('Ember Rising');
        $bottle = $this->bottle($this->me, $color);
        $this->add($color, ['bottle_id' => $bottle->id]);
        $this->manicure->forceFill(['visibility' => 'public'])->save();
        auth()->logout();

        $this->get("/manicures/{$this->manicure->id}")->assertDontSee('from my collection')->assertDontSee($bottle->id);
        $this->actingAs($this->me)->get("/manicures/{$this->manicure->id}")->assertSee('from my collection');
    }

    public function test_a_private_polish_on_a_public_manicure_shows_its_name_without_a_link_to_strangers(): void
    {
        $mine = $this->polish('My Secret', $this->me);
        $this->add($mine);
        $this->manicure->forceFill(['visibility' => 'public'])->save();
        auth()->logout();

        $this->get("/manicures/{$this->manicure->id}")->assertOk()
            ->assertSee('My Secret')
            ->assertDontSee($mine->fresh('brand')->url(), false);

        $this->actingAs($this->me)->get("/manicures/{$this->manicure->id}")->assertSee($mine->fresh('brand')->url(), false);
    }

    public function test_the_owner_is_prompted_to_add_polishes_when_there_are_none(): void
    {
        $this->actingAs($this->me)->get("/manicures/{$this->manicure->id}")
            ->assertSee("/manicures/{$this->manicure->id}/polishes", false)->assertSee('Add the polishes');
    }

    public function test_a_gap_left_by_a_cascade_delete_does_not_break_adding_or_moving(): void
    {
        $mine = $this->polish('B Private', $this->me);
        $this->add($this->polish('A'));
        $this->add($mine);
        $this->add($this->polish('C'));
        // Deleting a private polish removes its layer through the database cascade, leaving positions 0 and 2.
        $this->actingAs($this->me)->delete("/my-polishes/{$mine->id}");
        $this->assertSame([0, 2], $this->manicure->layers()->pluck('position')->all());

        $this->add($this->polish('D'))->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame(['A', 'C', 'D'], $this->names());

        $c = $this->manicure->layers()->get()[1];
        $this->actingAs($this->me)->post("/manicure-polishes/{$c->id}/move", ['direction' => 'up']);
        $this->assertSame(['C', 'A', 'D'], $this->names());

        $a = $this->manicure->layers()->get()[1];
        $this->actingAs($this->me)->post("/manicure-polishes/{$a->id}/move", ['direction' => 'down']);
        $this->assertSame(['C', 'D', 'A'], $this->names());
    }

    public function test_a_position_collision_from_a_simultaneous_add_is_retried_not_a_500(): void
    {
        $this->add($this->polish('A'));
        $failures = 0;
        // The first insert behaves as if another request just took the same slot.
        Event::listen('eloquent.creating: '.ManicurePolish::class, function () use (&$failures) {
            if ($failures++ === 0) {
                throw new UniqueConstraintViolationException('sqlite', 'insert', [], new \PDOException('UNIQUE constraint failed'));
            }
        });

        $this->add($this->polish('B'))->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(['A', 'B'], $this->names());
        $this->assertSame([0, 1], $this->manicure->layers()->pluck('position')->all());
        $this->assertSame(2, $failures, 'one failed attempt, then one successful retry');
    }

    public function test_if_the_collision_keeps_happening_the_user_gets_a_clear_message(): void
    {
        Event::listen('eloquent.creating: '.ManicurePolish::class, function () {
            throw new UniqueConstraintViolationException('sqlite', 'insert', [], new \PDOException('UNIQUE constraint failed'));
        });

        $this->add($this->polish('B'))->assertSessionHasErrors('polish_id');

        $this->assertSame(0, $this->manicure->layers()->count());
    }
}
