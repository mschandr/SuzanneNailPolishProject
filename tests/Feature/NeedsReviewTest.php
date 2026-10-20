<?php

namespace Tests\Feature;

use App\Models\ColorFamily;
use App\Models\Finish;
use App\Models\Polish;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsGalleryData;
use Tests\TestCase;

class NeedsReviewTest extends TestCase
{
    use BuildsGalleryData, RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create(['display_name' => 'Boss']);
    }

    private function flagged(string $name): Polish
    {
        $polish = $this->polish('Indie Co', $name);
        $polish->forceFill(['needs_review' => true])->save();

        return $polish;
    }

    public function test_it_lists_flagged_public_polishes_with_an_edit_link_and_nothing_else(): void
    {
        $flagged = $this->flagged('Needs Work');
        $this->polish('Indie Co', 'All Good');
        $private = $this->polish('Indie Co', 'Private One', null, [], $this->person('Suzanne'));
        $private->forceFill(['needs_review' => true])->save();

        $this->actingAs($this->admin)->get('/admin/needs-review')
            ->assertSee('Needs Work')->assertSee("/admin/polishes/{$flagged->id}/edit", false)
            ->assertDontSee('All Good')->assertDontSee('Private One');
    }

    public function test_it_says_what_is_missing(): void
    {
        $noColour = $this->flagged('No Colour');
        $noColour->finishes()->attach(Finish::create(['name' => 'Creme'])->id);
        $noFinish = $this->flagged('No Finish');
        $noFinish->update(['color_family_id' => ColorFamily::create(['name' => 'Red'])->id]);

        $page = $this->actingAs($this->admin)->get('/admin/needs-review');

        $page->assertSee('No Colour')->assertSee('colour family')->assertSee('No Finish')->assertSee('finish');
    }

    public function test_the_count_is_shown_on_the_admin_home_and_it_is_empty_when_all_is_done(): void
    {
        $this->actingAs($this->admin)->get('/admin/needs-review')->assertSee('Nothing needs review');
        $this->flagged('One');
        $this->flagged('Two');

        $this->actingAs($this->admin)->get('/admin')->assertSee('2 polishes need review');
    }

    public function test_saving_the_polish_in_the_admin_form_clears_the_flag(): void
    {
        $polish = $this->flagged('Needs Work');

        $this->actingAs($this->admin)->put("/admin/polishes/{$polish->id}", [
            'brand' => 'Indie Co', 'name' => 'Needs Work',
            'color_family_id' => ColorFamily::create(['name' => 'Red'])->id,
            'finish_ids' => [Finish::create(['name' => 'Creme'])->id],
        ])->assertRedirect();

        $this->assertFalse((bool) $polish->fresh()->needs_review);
        $this->actingAs($this->admin)->get('/admin/needs-review')->assertDontSee('Needs Work');
    }

    public function test_the_list_is_paginated(): void
    {
        foreach (range(1, 55) as $i) {
            $this->flagged(sprintf('Flagged %02d', $i));
        }

        $this->actingAs($this->admin)->get('/admin/needs-review')->assertSee('Flagged 01')->assertDontSee('Flagged 55');
        $this->actingAs($this->admin)->get('/admin/needs-review?page=2')->assertSee('Flagged 55');
    }

    public function test_a_flag_does_not_change_what_the_public_sees(): void
    {
        $polish = $this->flagged('Public Anyway');

        $this->get($polish->fresh('brand')->url())->assertOk()->assertSee('Public Anyway')->assertDontSee('review');
    }

    public function test_saving_without_a_finish_keeps_the_flag_until_the_finish_is_added(): void
    {
        $polish = $this->flagged('Needs Finish');
        $payload = ['brand' => 'Indie Co', 'name' => 'Needs Finish', 'color_family_id' => ColorFamily::create(['name' => 'Red'])->id];

        $this->actingAs($this->admin)->put("/admin/polishes/{$polish->id}", $payload)->assertRedirect();
        $this->assertTrue((bool) $polish->fresh()->needs_review, 'a colour alone is not enough: it still has no finish');
        $this->actingAs($this->admin)->get('/admin/needs-review')->assertSee('Needs Finish')->assertSee('missing finish');

        $this->actingAs($this->admin)->put("/admin/polishes/{$polish->id}", $payload + ['finish_ids' => [Finish::create(['name' => 'Creme'])->id]]);
        $this->assertFalse((bool) $polish->fresh()->needs_review);
    }

    public function test_a_polish_the_admin_creates_by_hand_without_a_finish_is_not_flagged(): void
    {
        $this->actingAs($this->admin)->post('/admin/polishes', [
            'brand' => 'Indie Co', 'name' => 'Hand Made', 'color_family_id' => ColorFamily::create(['name' => 'Red'])->id,
        ])->assertRedirect();

        $this->assertFalse((bool) Polish::where('name', 'Hand Made')->firstOrFail()->needs_review);
        $this->actingAs($this->admin)->get('/admin/needs-review')->assertDontSee('Hand Made');
    }
}
