<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsGalleryData;
use Tests\TestCase;

class WornByOthersTest extends TestCase
{
    use BuildsGalleryData, RefreshDatabase;

    public function test_a_polish_page_shows_public_manicures_that_list_it(): void
    {
        $polish = $this->polish('ILNP', 'Aurora');
        $other = $this->polish('ILNP', 'Borealis');
        $mine = $this->manicure($this->person('Suzanne'), [$polish], 'public');
        $unrelated = $this->manicure($this->person('Zed'), [$other], 'public');

        $this->get($polish->fresh('brand')->url())->assertOk()
            ->assertSee('Worn by others')
            ->assertSee($this->link($mine), false)
            ->assertDontSee($this->link($unrelated), false);
    }

    public function test_private_manicures_never_show_there(): void
    {
        $polish = $this->polish('ILNP', 'Aurora');
        $hidden = $this->manicure($this->person('Suzanne'), [$polish], 'private');

        $this->get($polish->fresh('brand')->url())->assertDontSee($this->link($hidden), false);
    }

    public function test_a_manicure_that_lists_the_polish_twice_appears_once(): void
    {
        $polish = $this->polish('ILNP', 'Aurora');
        $m = $this->manicure($this->person('Suzanne'), [$polish, $polish], 'public');

        $html = $this->get($polish->fresh('brand')->url())->getContent();

        $this->assertSame(1, substr_count($html, $this->link($m)));
    }

    public function test_it_shows_the_newest_twelve_and_links_to_the_filtered_gallery(): void
    {
        $polish = $this->polish('ILNP', 'Aurora');
        $u = $this->person('Suzanne');
        $made = [];
        foreach (range(1, 14) as $i) {
            $made[$i] = $this->manicure($u, [$polish], 'public', now()->subDays($i)->toDateString());
        }

        $page = $this->get($polish->fresh('brand')->url());

        $page->assertSee($this->link($made[1]), false)->assertSee($this->link($made[12]), false)
            ->assertDontSee($this->link($made[13]), false)->assertDontSee($this->link($made[14]), false)
            ->assertSee('/gallery?polish_id='.$polish->id, false);
    }

    public function test_the_section_is_absent_when_nobody_wore_it(): void
    {
        $polish = $this->polish('ILNP', 'Aurora');

        $this->get($polish->fresh('brand')->url())->assertOk()->assertDontSee('Worn by others');
    }

    public function test_a_private_polish_page_does_not_have_the_section(): void
    {
        $owner = $this->person('Suzanne');
        $secret = $this->polish('Secret Indie', 'Hidden Gem', null, [], $owner);
        $this->manicure($owner, [$secret], 'public');

        $this->actingAs($owner)->get($secret->fresh('brand')->url())->assertOk()->assertDontSee('Worn by others');
    }

    public function test_a_fresh_manicure_shows_immediately_despite_the_polish_page_cache(): void
    {
        $polish = $this->polish('ILNP', 'Aurora');
        $this->get($polish->fresh('brand')->url())->assertOk()->assertDontSee('Worn by others');

        $m = $this->manicure($this->person('Suzanne'), [$polish], 'public');

        $this->get($polish->fresh('brand')->url())->assertSee($this->link($m), false);
    }

    public function test_making_it_private_removes_it_from_the_polish_page_immediately(): void
    {
        $polish = $this->polish('ILNP', 'Aurora');
        $m = $this->manicure($this->person('Suzanne'), [$polish], 'public');
        $this->get($polish->fresh('brand')->url())->assertSee($this->link($m), false);

        $m->forceFill(['visibility' => 'private'])->save();

        $this->get($polish->fresh('brand')->url())->assertDontSee($this->link($m), false);
    }
}
