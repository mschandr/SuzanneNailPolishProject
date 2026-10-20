<?php

namespace Tests\Feature;

use App\Models\ColorFamily;
use App\Models\Finish;
use App\Models\ManicurePhoto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\BuildsGalleryData;
use Tests\TestCase;

class GalleryTest extends TestCase
{
    use BuildsGalleryData, RefreshDatabase;

    public function test_no_sign_in_is_needed(): void
    {
        $this->get('/gallery')->assertOk();
    }

    public function test_only_currently_public_manicures_are_listed(): void
    {
        $suzanne = $this->person('Suzanne');
        $public = $this->manicure($suzanne, [], 'public');
        $private = $this->manicure($suzanne, [], 'private');
        $followsDefault = $this->manicure($suzanne, [], null);

        $page = $this->get('/gallery')->assertOk();
        $page->assertSee($this->link($public), false)->assertSee($this->link($followsDefault), false)->assertDontSee($this->link($private), false);

        $suzanne->forceFill(['manicure_default_visibility' => 'private'])->save();

        $this->get('/gallery')->assertSee($this->link($public), false)->assertDontSee($this->link($followsDefault), false);
    }

    public function test_a_private_manicure_never_appears_even_when_it_matches_every_filter(): void
    {
        $suzanne = $this->person('Suzanne');
        $polish = $this->polish('ILNP', 'Aurora', 'Blue', ['Holographic']);
        $private = $this->manicure($suzanne, [$polish], 'private');

        $this->get('/gallery?brand=ilnp&polish=aurora&person=suzanne')->assertDontSee($this->link($private), false);
        $this->actingAs($suzanne)->get('/gallery')->assertDontSee($this->link($private), false);
    }

    public function test_newest_first_by_default(): void
    {
        $u = $this->person('Suzanne');
        $old = $this->manicure($u, [], 'public', '2026-01-01');
        $new = $this->manicure($u, [], 'public', '2026-10-01');

        $this->get('/gallery')->assertSeeInOrder([$this->link($new), $this->link($old)], false);
    }

    public function test_most_hearted_sorts_by_heart_count_then_newest(): void
    {
        $owner = $this->person('Owner');
        [$a, $b, $c] = [$this->person('A'), $this->person('B'), $this->person('C')];
        $none = $this->manicure($owner, [], 'public', '2026-10-20');
        $one = $this->manicure($owner, [], 'public', '2026-01-01');
        $twoOld = $this->manicure($owner, [], 'public', '2026-02-01');
        $twoNew = $this->manicure($owner, [], 'public', '2026-03-01');
        $this->heart($a, $one);
        $this->heart($a, $twoOld);
        $this->heart($b, $twoOld);
        $this->heart($a, $twoNew);
        $this->heart($b, $twoNew);

        $this->get('/gallery?sort=loved')->assertSeeInOrder(
            [$this->link($twoNew), $this->link($twoOld), $this->link($one), $this->link($none)],
            false,
        );
    }

    public function test_an_unknown_sort_falls_back_to_newest(): void
    {
        $u = $this->person('Suzanne');
        $old = $this->manicure($u, [], 'public', '2026-01-01');
        $new = $this->manicure($u, [], 'public', '2026-10-01');

        $this->get('/gallery?sort=bogus')->assertOk()->assertSeeInOrder([$this->link($new), $this->link($old)], false);
        $this->get('/gallery?sort[]=x')->assertOk();
    }

    public function test_the_tiles_show_the_heart_count_and_the_owners_display_name(): void
    {
        $owner = $this->person('Suzanne');
        $m = $this->manicure($owner, [], 'public');
        $this->heart($this->person('Fan1'), $m);
        $this->heart($this->person('Fan2'), $m);

        $this->get('/gallery')->assertSee('Suzanne')->assertSee('2 hearts');
    }

    // --- filters ------------------------------------------------------------

    private function scene(): array
    {
        $holo = $this->polish('ILNP', 'Aurora', 'Blue', ['Holographic']);
        $creme = $this->polish('ILNP', 'Plain Jane', 'Pink', ['Creme']);
        $taco = $this->polish('Holo Taco', 'Mango Tango', 'Orange', ['Holographic']);
        $s = $this->person('Suzanne');
        $z = $this->person('Zed');

        return [
            'holo' => $this->manicure($s, [$holo], 'public'),
            'creme' => $this->manicure($s, [$creme], 'public'),
            'taco' => $this->manicure($z, [$taco], 'public'),
            'empty' => $this->manicure($z, [], 'public'),
            'polishes' => compact('holo', 'creme', 'taco'),
        ];
    }

    private function listed(string $query, array $scene): array
    {
        $html = $this->get('/gallery'.$query)->assertOk()->getContent();

        return array_keys(array_filter(
            array_diff_key($scene, ['polishes' => 1]),
            fn ($m) => str_contains($html, $this->link($m)),
        ));
    }

    public function test_filter_by_brand_ignoring_case(): void
    {
        $scene = $this->scene();

        $this->assertEqualsCanonicalizing(['holo', 'creme'], $this->listed('?brand=ilnp', $scene));
        $this->assertEqualsCanonicalizing(['taco'], $this->listed('?brand=HOLO+TACO', $scene));
    }

    public function test_filter_by_polish_name(): void
    {
        $scene = $this->scene();

        $this->assertSame(['holo'], $this->listed('?polish=aurora', $scene));
        $this->assertSame(['creme'], $this->listed('?polish=jane', $scene));
    }

    public function test_filter_by_exact_polish_id(): void
    {
        $scene = $this->scene();

        $this->assertSame(['taco'], $this->listed('?polish_id='.$scene['polishes']['taco']->id, $scene));
    }

    public function test_filter_by_finish_and_color_family(): void
    {
        $scene = $this->scene();

        $this->assertEqualsCanonicalizing(['holo', 'taco'], $this->listed('?finish_id='.Finish::where('name_key', 'holographic')->value('id'), $scene));
        $this->assertSame(['creme'], $this->listed('?color_family_id='.ColorFamily::where('name_key', 'pink')->value('id'), $scene));
    }

    public function test_filter_by_display_name(): void
    {
        $scene = $this->scene();

        $this->assertEqualsCanonicalizing(['holo', 'creme'], $this->listed('?person=SUZ', $scene));
        $this->assertEqualsCanonicalizing(['taco', 'empty'], $this->listed('?person=zed', $scene));
    }

    public function test_filters_combine_and_must_all_match_the_same_polish(): void
    {
        $scene = $this->scene();

        // ILNP + Holographic: only the ILNP polish that is also holographic.
        $this->assertSame(['holo'], $this->listed('?brand=ilnp&finish_id='.Finish::where('name_key', 'holographic')->value('id'), $scene));
        // Holo Taco brand but a creme finish: nothing, even though both exist elsewhere on the site.
        $this->assertSame([], $this->listed('?brand=holo+taco&finish_id='.Finish::where('name_key', 'creme')->value('id'), $scene));
        // Polish filter plus person filter.
        $this->assertSame(['holo'], $this->listed('?polish=aurora&person=suzanne', $scene));
        $this->assertSame([], $this->listed('?polish=aurora&person=zed', $scene));
    }

    public function test_a_manicure_is_found_by_any_of_its_layers(): void
    {
        $base = $this->polish('Basecoat Co', 'Clear Base');
        $color = $this->polish('ILNP', 'Aurora', 'Blue', ['Holographic']);
        $m = $this->manicure($this->person('Suzanne'), [$base, $color], 'public');

        $this->get('/gallery?polish=aurora')->assertSee($this->link($m), false);
        $this->get('/gallery?polish=clear+base')->assertSee($this->link($m), false);
    }

    public function test_a_manicure_with_many_matching_layers_is_listed_once(): void
    {
        $a = $this->polish('ILNP', 'Aurora');
        $b = $this->polish('ILNP', 'Borealis');
        $m = $this->manicure($this->person('Suzanne'), [$a, $b], 'public');

        $html = $this->get('/gallery?brand=ilnp')->getContent();

        $this->assertSame(1, substr_count($html, $this->link($m)));
    }

    public function test_private_polishes_are_not_searchable_and_do_not_make_a_manicure_findable(): void
    {
        $suzanne = $this->person('Suzanne');
        $secret = $this->polish('Secret Indie', 'Hidden Gem', 'Red', ['Creme'], $suzanne);
        $m = $this->manicure($suzanne, [$secret], 'public');

        $this->get('/gallery?polish=hidden')->assertDontSee($this->link($m), false);
        $this->get('/gallery?brand=secret')->assertDontSee($this->link($m), false);
        $this->get('/gallery?polish_id='.$secret->id)->assertDontSee($this->link($m), false);
        // It still appears with no filter, just not by its private polish.
        $this->get('/gallery')->assertSee($this->link($m), false);
    }

    public function test_search_terms_are_literal_and_junk_is_survivable(): void
    {
        $scene = $this->scene();

        $this->assertSame([], $this->listed('?polish=%25', $scene));
        $this->assertSame([], $this->listed('?brand=_', $scene));
        $this->get('/gallery?brand[]=x&polish[]=y&person[]=z&finish_id[]=1&sort[]=q&page[]=2')->assertOk();
        $this->get('/gallery?finish_id=not-a-uuid&color_family_id=nope&polish_id=zzz')->assertOk();
    }

    public function test_filter_values_are_kept_in_the_form_and_in_the_pagination_links(): void
    {
        $u = $this->person('Suzanne');
        $polish = $this->polish('ILNP', 'Aurora');
        foreach (range(1, 26) as $i) {
            $this->manicure($u, [$polish], 'public', now()->subDays($i)->toDateString());
        }

        $page = $this->get('/gallery?brand=ilnp&sort=loved')->assertOk();
        $page->assertSee('value="ilnp"', false)->assertSee('brand=ilnp', false)->assertSee('sort=loved', false);
        $this->get('/gallery?brand=ilnp&sort=loved&page=2')->assertOk();
    }

    public function test_pagination_is_24_per_page(): void
    {
        $u = $this->person('Suzanne');
        $made = [];
        foreach (range(1, 30) as $i) {
            $made[$i] = $this->manicure($u, [], 'public', now()->subDays($i)->toDateString());
        }

        $first = $this->get('/gallery')->getContent();
        $this->assertSame(24, preg_match_all('#/manicures/[0-9a-f-]{36}"#', $first));
        $this->get('/gallery?page=2')->assertSee($this->link($made[30]), false)->assertDontSee($this->link($made[1]), false);
    }

    public function test_the_gallery_does_not_run_a_query_per_tile(): void
    {
        $u = $this->person('Suzanne');
        $polish = $this->polish('ILNP', 'Aurora');
        foreach (range(1, 12) as $_) {
            $this->manicure($this->person('P'.$_), [$polish], 'public');
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get('/gallery?brand=ilnp')->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThan(15, $count, "expected a handful of queries for 12 tiles, got {$count}");
    }

    public function test_an_empty_gallery_says_so(): void
    {
        $this->get('/gallery')->assertOk()->assertSee('No manicures');
    }

    public function test_a_manicure_with_no_photo_still_has_a_clickable_tile(): void
    {
        $m = $this->manicure($this->person('Suzanne'), [], 'public');
        ManicurePhoto::query()->delete();

        $this->get('/gallery')->assertOk()->assertSee($this->link($m), false)->assertSee('No photo');
    }

    public function test_manicures_tied_on_every_sort_key_keep_a_stable_order_across_pages(): void
    {
        $u = $this->person('Suzanne');
        // 30 identical manicures: only the final tiebreaker can keep pages from overlapping or skipping.
        foreach (range(1, 30) as $_) {
            $this->manicure($u, [], 'public', '2026-10-01');
        }

        foreach (['new', 'loved'] as $sort) {
            $ids = [];
            foreach ([1, 2] as $page) {
                preg_match_all('#/manicures/([0-9a-f-]{36})"#', $this->get("/gallery?sort={$sort}&page={$page}")->getContent(), $m);
                $ids = array_merge($ids, array_unique($m[1]));
            }
            $this->assertCount(30, array_unique($ids), "every manicure appears exactly once across pages ({$sort})");
        }
    }
}
