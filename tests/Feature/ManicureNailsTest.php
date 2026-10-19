<?php

namespace Tests\Feature;

use App\Models\Bottle;
use App\Models\Manicure;
use App\Models\ManicurePolish;
use App\Models\Polish;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\BuildsGalleryData;
use Tests\Support\TestImages;
use Tests\TestCase;

class ManicureNailsTest extends TestCase
{
    use BuildsGalleryData, RefreshDatabase;

    private User $me;

    private Manicure $manicure;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('photos');
        $this->me = $this->person('Suzanne');
        $response = $this->actingAs($this->me)->post('/manicures', [
            'worn_on' => '2026-10-15', 'visibility' => 'public', 'photos' => [TestImages::upload('a.jpg', 400, 300)],
        ]);
        auth()->logout();
        $this->manicure = Manicure::findOrFail(basename($response->headers->get('Location')));
    }

    private function mode(string $mode, ?User $as = null, ?Manicure $m = null)
    {
        return $this->actingAs($as ?? $this->me)->put('/manicures/'.($m ?? $this->manicure)->id.'/polish-mode', ['mode' => $mode]);
    }

    private function add(Polish $polish, ?string $target = null, array $extra = [], ?User $as = null)
    {
        $data = ['polish_id' => $polish->id] + $extra + ($target ? ['target' => $target] : []);

        return $this->actingAs($as ?? $this->me)->post("/manicures/{$this->manicure->id}/polishes", $data);
    }

    /** Polish names on one list ('hand' or a nail key), in order. */
    private function names(string $target = 'hand'): array
    {
        return $this->manicure->layers()->where('target', $target)->with('polish')->get()->map(fn ($l) => $l->polish->name)->all();
    }

    private function p(string $name): Polish
    {
        return Polish::firstWhere('name', $name) ?? $this->polish('ILNP', $name);
    }

    // --- the mode toggle ------------------------------------------------------------------------

    public function test_a_manicure_starts_in_whole_hand_mode(): void
    {
        $this->assertSame('hand', $this->manicure->fresh()->polish_mode);
    }

    public function test_the_owner_can_switch_modes_back_and_forth(): void
    {
        $this->mode('nails')->assertRedirect("/manicures/{$this->manicure->id}/polishes");
        $this->assertSame('nails', $this->manicure->fresh()->polish_mode);

        $this->mode('hand')->assertRedirect();
        $this->assertSame('hand', $this->manicure->fresh()->polish_mode);
    }

    public function test_an_unknown_mode_is_refused(): void
    {
        $this->mode('toes')->assertSessionHasErrors('mode');
        $this->actingAs($this->me)->put("/manicures/{$this->manicure->id}/polish-mode", [])->assertSessionHasErrors('mode');

        $this->assertSame('hand', $this->manicure->fresh()->polish_mode);
    }

    public function test_only_the_owner_can_switch(): void
    {
        $this->put("/manicures/{$this->manicure->id}/polish-mode", ['mode' => 'nails'])->assertRedirect('/login');
        $this->mode('nails', $this->person('Stranger'))->assertNotFound();

        $this->assertSame('hand', $this->manicure->fresh()->polish_mode);
    }

    public function test_a_hidden_manicure_cannot_be_switched(): void
    {
        $this->manicure->forceFill(['hidden_at' => now()])->save();

        $this->mode('nails')->assertForbidden();
    }

    public function test_switching_never_loses_either_list(): void
    {
        $this->add($this->p('Hand Polish'));
        $this->mode('nails');
        $this->add($this->p('Ring Polish'), 'left_ring');

        $this->mode('hand');
        $this->assertSame(['Hand Polish'], $this->names('hand'));
        $this->assertSame(['Ring Polish'], $this->names('left_ring'), 'the nail list is dormant, not deleted');

        $this->mode('nails');
        $this->assertSame(['Hand Polish'], $this->names('hand'));
    }

    // --- adding to a nail ------------------------------------------------------------------------

    public function test_each_nail_has_its_own_ordered_list_starting_at_zero(): void
    {
        $this->mode('nails');

        $this->add($this->p('A'), 'left_thumb');
        $this->add($this->p('B'), 'left_thumb');
        $this->add($this->p('C'), 'right_pinky');

        $this->assertSame(['A', 'B'], $this->names('left_thumb'));
        $this->assertSame(['C'], $this->names('right_pinky'));
        $this->assertSame([0, 1], $this->manicure->layers()->where('target', 'left_thumb')->pluck('position')->all());
        $this->assertSame([0], $this->manicure->layers()->where('target', 'right_pinky')->pluck('position')->all());
    }

    public function test_every_one_of_the_ten_nails_is_accepted(): void
    {
        $this->mode('nails');
        $polish = $this->p('A');

        foreach (array_keys(Manicure::NAILS) as $nail) {
            $this->add($polish, $nail)->assertSessionHasNoErrors();
        }

        $this->assertSame(10, $this->manicure->layers()->count());
        $this->assertCount(10, Manicure::NAILS);
    }

    public function test_an_unknown_nail_is_refused(): void
    {
        $this->mode('nails');

        $this->add($this->p('A'), 'left_toe')->assertSessionHasErrors('target');
        $this->add($this->p('A'), 'hand')->assertSessionHasErrors('target');

        $this->assertSame(0, $this->manicure->layers()->count());
    }

    public function test_adding_to_a_nail_while_in_whole_hand_mode_is_refused_with_a_hint(): void
    {
        $this->add($this->p('A'), 'left_ring')->assertSessionHasErrors(['target' => 'Switch to per-nail mode first.']);

        $this->assertSame(0, $this->manicure->layers()->count());
    }

    public function test_adding_to_the_hand_list_while_in_per_nail_mode_is_refused(): void
    {
        $this->mode('nails');

        $this->add($this->p('A'))->assertSessionHasErrors('target');
        $this->add($this->p('A'), 'hand')->assertSessionHasErrors('target');
    }

    public function test_the_same_polish_can_be_listed_twice_on_one_nail(): void
    {
        $this->mode('nails');
        $p = $this->p('Ember');

        $this->add($p, 'left_ring');
        $this->add($p, 'left_ring');

        $this->assertSame(['Ember', 'Ember'], $this->names('left_ring'));
    }

    public function test_each_nail_is_capped_at_ten_layers_independently(): void
    {
        $this->mode('nails');
        $p = $this->p('A');
        foreach (range(1, 10) as $_) {
            $this->add($p, 'left_ring');
        }

        $this->add($p, 'left_ring')->assertSessionHasErrors(['polish_id' => 'This list can have at most 10 layers.']);
        $this->add($p, 'left_thumb')->assertSessionHasNoErrors();

        $this->assertSame(10, $this->manicure->layers()->where('target', 'left_ring')->count());
    }

    public function test_a_bottle_can_be_linked_on_a_nail_under_the_same_rules(): void
    {
        $this->mode('nails');
        $p = $this->p('Ember');
        $mine = new Bottle(['polish_id' => $p->id]);
        $mine->user_id = $this->me->id;
        $mine->save();
        $other = $this->p('Other');
        $wrong = new Bottle(['polish_id' => $other->id]);
        $wrong->user_id = $this->me->id;
        $wrong->save();

        $this->add($p, 'left_ring', ['bottle_id' => $mine->id])->assertSessionHasNoErrors();
        $this->add($p, 'left_ring', ['bottle_id' => $wrong->id])->assertSessionHasErrors('bottle_id');

        $this->assertSame($mine->id, $this->manicure->layers()->where('target', 'left_ring')->firstOrFail()->bottle_id);
    }

    public function test_someone_elses_private_polish_cannot_go_on_a_nail(): void
    {
        $this->mode('nails');
        $theirs = $this->polish('ILNP', 'Their Secret', null, [], $this->person('Other'));

        $this->add($theirs, 'left_ring')->assertSessionHasErrors('polish_id');
    }

    // --- reordering and removing within a nail -----------------------------------------------------

    public function test_moving_swaps_only_within_that_nail(): void
    {
        $this->mode('nails');
        $this->add($this->p('A'), 'left_ring');
        $this->add($this->p('B'), 'left_ring');
        $this->add($this->p('X'), 'left_thumb');
        $b = $this->manicure->layers()->where('target', 'left_ring')->orderBy('position')->get()[1];

        $this->actingAs($this->me)->post("/manicure-polishes/{$b->id}/move", ['direction' => 'up']);

        $this->assertSame(['B', 'A'], $this->names('left_ring'));
        $this->assertSame(['X'], $this->names('left_thumb'));
        $this->actingAs($this->me)->post("/manicure-polishes/{$b->id}/move", ['direction' => 'up']);
        $this->assertSame(['B', 'A'], $this->names('left_ring'), 'already first on its nail: it does not jump to another list');
    }

    public function test_a_move_goes_back_to_that_nails_page(): void
    {
        $this->mode('nails');
        $this->add($this->p('A'), 'left_ring');
        $layer = $this->manicure->layers()->firstOrFail();

        $this->actingAs($this->me)->post("/manicure-polishes/{$layer->id}/move", ['direction' => 'down'])
            ->assertRedirect("/manicures/{$this->manicure->id}/polishes?nail=left_ring");
    }

    public function test_removing_closes_the_gap_on_that_nail_only(): void
    {
        $this->mode('nails');
        foreach (['A', 'B', 'C'] as $n) {
            $this->add($this->p($n), 'left_ring');
        }
        $this->add($this->p('X'), 'left_thumb');
        $this->add($this->p('Y'), 'left_thumb');
        $b = $this->manicure->layers()->where('target', 'left_ring')->orderBy('position')->get()[1];

        $this->actingAs($this->me)->delete("/manicure-polishes/{$b->id}")
            ->assertRedirect("/manicures/{$this->manicure->id}/polishes?nail=left_ring");

        $this->assertSame(['A', 'C'], $this->names('left_ring'));
        $this->assertSame([0, 1], $this->manicure->layers()->where('target', 'left_ring')->pluck('position')->all());
        $this->assertSame([0, 1], $this->manicure->layers()->where('target', 'left_thumb')->pluck('position')->all());
    }

    public function test_a_gap_on_a_nail_left_by_a_cascade_delete_does_not_break_adding(): void
    {
        $this->mode('nails');
        $secret = $this->polish('ILNP', 'My Secret', null, [], $this->me);
        $this->add($this->p('A'), 'left_ring');
        $this->add($secret, 'left_ring');
        $this->add($this->p('C'), 'left_ring');
        $this->actingAs($this->me)->delete("/my-polishes/{$secret->id}");

        $this->add($this->p('D'), 'left_ring')->assertSessionHasNoErrors();

        $this->assertSame(['A', 'C', 'D'], $this->names('left_ring'));
    }

    public function test_someone_elses_nail_layer_is_404_to_change(): void
    {
        $this->mode('nails');
        $this->add($this->p('A'), 'left_ring');
        $layer = $this->manicure->layers()->firstOrFail();
        $stranger = $this->person('Stranger');

        $this->actingAs($stranger)->post("/manicure-polishes/{$layer->id}/move", ['direction' => 'down'])->assertNotFound();
        $this->actingAs($stranger)->delete("/manicure-polishes/{$layer->id}")->assertNotFound();
    }

    // --- copy the whole-hand list onto every nail --------------------------------------------------

    private function copy(?User $as = null)
    {
        return $this->actingAs($as ?? $this->me)->post("/manicures/{$this->manicure->id}/polishes/copy-to-nails");
    }

    public function test_the_hand_list_can_be_copied_onto_all_ten_nails(): void
    {
        $base = $this->p('Base');
        $color = $this->p('Colour');
        $bottle = new Bottle(['polish_id' => $color->id]);
        $bottle->user_id = $this->me->id;
        $bottle->save();
        $this->add($base);
        $this->add($color, null, ['bottle_id' => $bottle->id]);
        $this->mode('nails');

        $this->copy()->assertRedirect()->assertSessionHasNoErrors();

        foreach (array_keys(Manicure::NAILS) as $nail) {
            $this->assertSame(['Base', 'Colour'], $this->names($nail), $nail);
        }
        $this->assertSame($bottle->id, $this->manicure->layers()->where('target', 'left_ring')->where('position', 1)->firstOrFail()->bottle_id);
        $this->assertSame(['Base', 'Colour'], $this->names('hand'), 'the whole-hand list is untouched');
    }

    public function test_copying_replaces_whatever_the_nails_had(): void
    {
        $this->add($this->p('Base'));
        $this->mode('nails');
        $this->add($this->p('Accent'), 'left_ring');
        $this->add($this->p('Accent2'), 'left_ring');

        $this->copy();

        $this->assertSame(['Base'], $this->names('left_ring'));
    }

    public function test_copying_needs_per_nail_mode_and_a_non_empty_hand_list(): void
    {
        $this->add($this->p('Base'));
        $this->copy()->assertSessionHasErrors('copy'); // still in whole-hand mode
        $this->assertSame(1, $this->manicure->layers()->count());

        $this->manicure->layers()->delete();
        $this->mode('nails');
        $this->copy()->assertSessionHasErrors('copy'); // empty hand list
        $this->assertSame(0, $this->manicure->layers()->count());
    }

    public function test_only_the_owner_can_copy_and_not_while_hidden(): void
    {
        $this->add($this->p('Base'));
        $this->mode('nails');

        $this->copy($this->person('Stranger'))->assertNotFound();
        $this->manicure->forceFill(['hidden_at' => now()])->save();
        $this->copy()->assertForbidden();

        $this->assertSame(1, $this->manicure->layers()->count());
    }

    // --- display -----------------------------------------------------------------------------------

    public function test_in_whole_hand_mode_the_page_shows_only_the_hand_list(): void
    {
        $this->add($this->p('Hand Polish'));
        $this->mode('nails');
        $this->add($this->p('Ring Polish'), 'left_ring');
        $this->mode('hand');
        auth()->logout();

        $this->get("/manicures/{$this->manicure->id}")->assertSee('Hand Polish')->assertDontSee('Ring Polish')->assertDontSee('Left ring');
    }

    public function test_in_per_nail_mode_the_page_shows_each_nail_with_its_polishes_in_order(): void
    {
        $this->add($this->p('Hand Polish'));
        $this->mode('nails');
        $this->add($this->p('Base'), 'left_ring');
        $this->add($this->p('Glitter'), 'left_ring');
        $this->add($this->p('Thumb Colour'), 'right_thumb');
        auth()->logout();

        $this->get("/manicures/{$this->manicure->id}")->assertOk()
            ->assertDontSee('Hand Polish')
            ->assertSeeInOrder(['Left ring', 'Base', 'Glitter', 'Right thumb', 'Thumb Colour']);
    }

    public function test_nails_with_nothing_listed_are_not_shown(): void
    {
        $this->mode('nails');
        $this->add($this->p('Base'), 'left_ring');
        auth()->logout();

        $page = $this->get("/manicures/{$this->manicure->id}")->assertSee('Left ring');
        foreach (['Left thumb', 'Right pinky'] as $empty) {
            $page->assertDontSee($empty);
        }
    }

    public function test_bottle_notes_stay_owner_only_on_nails_too(): void
    {
        $this->mode('nails');
        $p = $this->p('Ember');
        $b = new Bottle(['polish_id' => $p->id]);
        $b->user_id = $this->me->id;
        $b->save();
        $this->add($p, 'left_ring', ['bottle_id' => $b->id]);
        auth()->logout();

        $this->get("/manicures/{$this->manicure->id}")->assertDontSee('from my collection');
        $this->actingAs($this->me)->get("/manicures/{$this->manicure->id}")->assertSee('from my collection');
    }

    public function test_a_private_polish_on_a_nail_shows_its_name_but_no_link_to_strangers(): void
    {
        $this->mode('nails');
        $mine = $this->polish('ILNP', 'My Secret', null, [], $this->me);
        $this->add($mine, 'left_ring');
        auth()->logout();

        $this->get("/manicures/{$this->manicure->id}")->assertSee('My Secret')->assertDontSee($mine->fresh('brand')->url(), false);
        $this->actingAs($this->me)->get("/manicures/{$this->manicure->id}")->assertSee($mine->fresh('brand')->url(), false);
    }

    public function test_the_owner_is_prompted_when_the_active_mode_has_nothing_even_if_the_other_does(): void
    {
        $this->add($this->p('Hand Polish'));
        $this->mode('nails');

        $this->actingAs($this->me)->get("/manicures/{$this->manicure->id}")->assertSee('Add the polishes');
    }

    // --- searches only see the active mode ----------------------------------------------------------

    public function test_the_gallery_only_matches_the_layers_of_the_active_mode(): void
    {
        $handOnly = $this->p('Hand Shade');
        $nailOnly = $this->p('Nail Shade');
        $this->add($handOnly);
        $this->mode('nails');
        $this->add($nailOnly, 'left_ring');
        $url = "/manicures/{$this->manicure->id}";
        auth()->logout();

        $this->get('/gallery?polish=nail+shade')->assertSee($url, false);
        $this->get('/gallery?polish=hand+shade')->assertDontSee($url, false);

        $this->mode('hand');
        auth()->logout();
        $this->get('/gallery?polish=hand+shade')->assertSee($url, false);
        $this->get('/gallery?polish=nail+shade')->assertDontSee($url, false);
    }

    public function test_the_polish_page_worn_by_list_follows_the_active_mode_too(): void
    {
        $nailOnly = $this->p('Nail Shade');
        $this->mode('nails');
        $this->add($nailOnly, 'left_ring');
        $url = "/manicures/{$this->manicure->id}";
        auth()->logout();
        $page = $nailOnly->fresh('brand')->url();

        $this->get($page)->assertSee($url, false);

        $this->mode('hand');
        auth()->logout();
        $this->get($page)->assertDontSee($url, false);
    }

    // --- the labelling page -------------------------------------------------------------------------

    public function test_the_page_offers_the_switch_to_per_nail_mode_with_an_explanation(): void
    {
        $this->actingAs($this->me)->get("/manicures/{$this->manicure->id}/polishes")
            ->assertSee('Switch to per-nail mode')
            ->assertSee('action="/manicures/'.$this->manicure->id.'/polish-mode"', false)
            ->assertDontSee('Left thumb');
    }

    public function test_in_per_nail_mode_the_page_shows_all_ten_nails_with_layer_counts(): void
    {
        $this->mode('nails');
        $this->add($this->p('A'), 'left_ring');
        $this->add($this->p('B'), 'left_ring');

        $page = $this->actingAs($this->me)->get("/manicures/{$this->manicure->id}/polishes")->assertOk()
            ->assertSee('Switch to whole-hand mode');
        foreach (Manicure::NAILS as $key => $label) {
            $page->assertSee($label)->assertSee("nail={$key}", false);
        }
        $page->assertSee('Left ring (2)');
    }

    public function test_the_selected_nails_list_and_search_forms_target_that_nail(): void
    {
        $this->mode('nails');
        $this->add($this->p('Ring Polish'), 'left_ring');
        $this->p('Searchable');

        $html = $this->actingAs($this->me)->get("/manicures/{$this->manicure->id}/polishes?nail=left_ring&q=searchable")->getContent();

        $this->assertStringContainsString('Ring Polish', $html);
        $this->assertStringContainsString('name="target" value="left_ring"', $html);
        $this->assertStringContainsString('name="nail" value="left_ring"', $html, 'the search keeps the nail');
    }

    public function test_a_bad_nail_in_the_url_falls_back_to_the_first_nail(): void
    {
        $this->mode('nails');
        $this->add($this->p('Thumb Polish'), 'left_thumb');

        foreach (['left_toe', 'hand', '', 'x'] as $bad) {
            $this->actingAs($this->me)->get("/manicures/{$this->manicure->id}/polishes?nail={$bad}")->assertOk()->assertSee('Thumb Polish');
        }
        $this->actingAs($this->me)->get("/manicures/{$this->manicure->id}/polishes?nail[]=x")->assertOk();
    }

    public function test_the_copy_button_appears_only_in_per_nail_mode_with_a_hand_list(): void
    {
        $this->add($this->p('Base'));
        $this->actingAs($this->me)->get("/manicures/{$this->manicure->id}/polishes")->assertDontSee('copy-to-nails');

        $this->mode('nails');
        $this->actingAs($this->me)->get("/manicures/{$this->manicure->id}/polishes")
            ->assertSee('action="/manicures/'.$this->manicure->id.'/polishes/copy-to-nails"', false);
    }
}
