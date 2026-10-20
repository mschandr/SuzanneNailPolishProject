<?php

namespace Tests\Feature;

use App\Models\Manicure;
use App\Models\ManicurePolish;
use App\Models\ModerationLog;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\Support\BuildsGalleryData;
use Tests\Support\TestImages;
use Tests\TestCase;

class ModerationHideDeleteTest extends TestCase
{
    use BuildsGalleryData, RefreshDatabase;

    private User $admin;

    private User $owner;

    private Manicure $m;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('photos');
        $this->admin = User::factory()->admin()->create(['display_name' => 'Boss']);
        $this->owner = $this->person('Suzanne');
        $this->m = $this->realManicure($this->owner);
    }

    /** A Manicure created through the app so it has real stored photo files. */
    private function realManicure(User $owner, string $caption = 'Autumn vibes'): Manicure
    {
        $response = $this->actingAs($owner)->post('/manicures', [
            'worn_on' => '2026-10-15', 'caption' => $caption, 'visibility' => 'public',
            'photos' => [TestImages::upload('a.jpg', 400, 300)],
        ]);
        auth()->logout();

        return Manicure::findOrFail(basename($response->headers->get('Location')));
    }

    private function report(Manicure $m, string $status = 'open'): Report
    {
        $r = new Report(['reason' => 'spam']);
        $r->manicure_id = $m->id;
        $r->reporter_id = $this->person('R'.uniqid())->id;
        $r->status = $status;
        $r->save();

        return $r;
    }

    private function hide(?string $reason = 'Breaks the rules')
    {
        return $this->actingAs($this->admin)->post("/admin/manicures/{$this->m->id}/hide", ['reason' => $reason]);
    }

    public function test_only_a_reported_manicure_can_be_moderated(): void
    {
        $this->actingAs($this->admin)->post("/admin/manicures/{$this->m->id}/hide")->assertNotFound();
        $this->actingAs($this->admin)->delete("/admin/manicures/{$this->m->id}")->assertNotFound();

        $this->assertNull($this->m->fresh()->hidden_at);
        $this->assertNotNull(Manicure::find($this->m->id));
    }

    public function test_non_admins_cannot_hide_restore_or_delete(): void
    {
        $this->report($this->m);
        $plain = $this->person('Plain');

        $this->actingAs($plain)->post("/admin/manicures/{$this->m->id}/hide")->assertForbidden();
        $this->actingAs($plain)->post("/admin/manicures/{$this->m->id}/restore")->assertForbidden();
        $this->actingAs($plain)->delete("/admin/manicures/{$this->m->id}")->assertForbidden();
        $this->actingAs($this->owner)->post("/admin/manicures/{$this->m->id}/hide")->assertForbidden();

        $this->assertNull($this->m->fresh()->hidden_at);
    }

    public function test_hiding_records_when_and_why_and_closes_the_open_reports_as_actioned(): void
    {
        $this->report($this->m);
        $this->report($this->m);

        $this->hide('Breaks rule 3')->assertRedirect('/admin/moderation');

        $fresh = $this->m->fresh();
        $this->assertNotNull($fresh->hidden_at);
        $this->assertSame('Breaks rule 3', $fresh->hidden_reason);
        $this->assertSame(2, Report::where('status', 'actioned')->whereNotNull('handled_at')->count());
        $this->assertSame(0, Report::where('status', 'open')->count());
    }

    public function test_hiding_is_logged(): void
    {
        $this->report($this->m);

        $this->hide('Breaks rule 3');

        $entry = ModerationLog::firstOrFail();
        $this->assertSame('hide', $entry->action);
        $this->assertSame($this->m->id, $entry->target_id);
        $this->assertSame('Breaks rule 3', $entry->note);
        $this->assertStringContainsString('Suzanne', $entry->summary);
    }

    public function test_a_hidden_manicure_disappears_from_every_public_surface(): void
    {
        $polish = $this->polish('ILNP', 'Aurora');
        $layer = new ManicurePolish;
        $layer->manicure_id = $this->m->id;
        $layer->polish_id = $polish->id;
        $layer->position = 0;
        $layer->save();
        $fan = $this->person('Fan');
        $this->heart($fan, $this->m);
        $this->report($this->m);
        $url = $this->link($this->m);
        $photo = "/photos/{$this->m->photos->first()->id}/full";
        $this->get($url)->assertOk(); // visible before

        $this->hide();
        auth()->logout();

        $this->get('/gallery')->assertDontSee($url, false);
        $this->get('/gallery?brand=ilnp')->assertDontSee($url, false);
        $this->get($polish->fresh('brand')->url())->assertDontSee($url, false);
        $this->get("/people/{$this->owner->id}")->assertDontSee($url, false);
        $this->get('/credits')->assertDontSee($url, false);
        $this->get($url)->assertNotFound();
        $this->get($photo)->assertNotFound();
        $this->actingAs($fan)->get('/liked')->assertDontSee($url, false);
        $this->actingAs($fan)->post("{$url}/heart")->assertNotFound();
        $this->actingAs($fan)->post("{$url}/report", ['reason' => 'spam'])->assertNotFound();
    }

    public function test_the_owner_still_sees_it_with_a_notice_and_cannot_unhide_it(): void
    {
        $this->report($this->m);
        $this->hide('Breaks rule 3');
        auth()->logout();

        $this->actingAs($this->owner)->get($this->link($this->m))->assertOk()
            ->assertSee('hidden by a moderator')->assertSee('Breaks rule 3');
        $this->actingAs($this->owner)->get("/photos/{$this->m->photos->first()->id}/full")->assertOk();
        $this->actingAs($this->owner)->get('/manicures')->assertSee('Hidden by a moderator');

        // Making it "public" again by editing does not lift the moderator's decision.
        $this->actingAs($this->owner)->put($this->link($this->m), ['worn_on' => '2026-10-15', 'visibility' => 'public']);
        auth()->logout();
        $this->get($this->link($this->m))->assertNotFound();
        $this->assertNotNull($this->m->fresh()->hidden_at);
    }

    public function test_the_owner_can_still_delete_their_hidden_manicure(): void
    {
        $this->report($this->m);
        $this->hide();

        $this->actingAs($this->owner)->delete($this->link($this->m))->assertRedirect('/manicures');

        $this->assertNull(Manicure::find($this->m->id));
    }

    public function test_the_admin_can_still_view_a_hidden_manicure_and_its_photo(): void
    {
        $this->report($this->m);
        $this->hide();

        $this->actingAs($this->admin)->get($this->link($this->m))->assertOk();
        $this->actingAs($this->admin)->get("/photos/{$this->m->photos->first()->id}/full")->assertOk();
    }

    public function test_hidden_photos_are_never_publicly_cacheable(): void
    {
        $this->report($this->m);
        $this->hide();

        $cache = $this->actingAs($this->owner)->get("/photos/{$this->m->photos->first()->id}/thumb")->headers->get('Cache-Control');

        $this->assertStringContainsString('no-store', $cache);
        $this->assertStringNotContainsString('public', $cache);
    }

    public function test_restoring_brings_it_back_and_is_logged(): void
    {
        $this->report($this->m);
        $this->hide();

        $this->actingAs($this->admin)->post("/admin/manicures/{$this->m->id}/restore")->assertRedirect('/admin/moderation');

        $fresh = $this->m->fresh();
        $this->assertNull($fresh->hidden_at);
        $this->assertNull($fresh->hidden_reason);
        auth()->logout();
        $this->get('/gallery')->assertSee($this->link($this->m), false);
        $this->assertSame(['hide', 'restore'], ModerationLog::orderBy('id')->pluck('action')->all());
    }

    public function test_restoring_something_not_hidden_is_404(): void
    {
        $this->report($this->m);

        $this->actingAs($this->admin)->post("/admin/manicures/{$this->m->id}/restore")->assertNotFound();
    }

    public function test_hidden_manicures_are_listed_for_the_admin_so_they_can_be_restored(): void
    {
        $this->report($this->m);
        $this->hide('Breaks rule 3');

        $this->actingAs($this->admin)->get('/admin/moderation')->assertSee('Hidden manicures')->assertSee('Breaks rule 3')
            ->assertSee('action="/admin/manicures/'.$this->m->id.'/restore"', false);
    }

    public function test_deleting_removes_the_manicure_its_photos_files_and_reports(): void
    {
        $this->report($this->m);
        $photo = $this->m->photos->first();
        [$path, $thumb] = [$photo->path, $photo->thumb_path];
        Storage::disk('photos')->assertExists($path);

        $this->actingAs($this->admin)->delete("/admin/manicures/{$this->m->id}")->assertRedirect('/admin/moderation');

        $this->assertNull(Manicure::find($this->m->id));
        $this->assertSame(0, Report::count());
        Storage::disk('photos')->assertMissing($path);
        Storage::disk('photos')->assertMissing($thumb);
    }

    public function test_deleting_leaves_a_log_entry_that_survives_the_manicure(): void
    {
        $this->report($this->m);
        $id = $this->m->id;

        $this->actingAs($this->admin)->delete("/admin/manicures/{$id}", ['reason' => 'Spam']);

        $entry = ModerationLog::firstOrFail();
        $this->assertSame('delete_manicure', $entry->action);
        $this->assertSame($id, $entry->target_id);
        $this->assertStringContainsString('Suzanne', $entry->summary);
        $this->assertStringContainsString('Autumn vibes', $entry->summary);
    }

    public function test_deleting_another_manicure_leaves_it_alone(): void
    {
        $keep = $this->realManicure($this->owner, 'Keep me');
        $this->report($this->m);

        $this->actingAs($this->admin)->delete("/admin/manicures/{$this->m->id}");

        $this->assertNotNull(Manicure::find($keep->id));
        Storage::disk('photos')->assertExists($keep->photos->first()->path);
    }

    public function test_hiding_twice_changes_nothing_and_logs_once(): void
    {
        $this->report($this->m);
        $this->hide('First reason');
        $firstHiddenAt = $this->m->fresh()->hidden_at;
        $this->travel(5)->minutes();

        $this->hide('Second reason')->assertRedirect('/admin/moderation')->assertSessionHas('status', 'Already hidden.');

        $fresh = $this->m->fresh();
        $this->assertSame('First reason', $fresh->hidden_reason);
        $this->assertEquals($firstHiddenAt, $fresh->hidden_at);
        $this->assertSame(1, ModerationLog::where('action', 'hide')->count());
    }

    public function test_the_owner_cannot_edit_a_hidden_manicure_in_any_way(): void
    {
        $this->report($this->m);
        $this->hide();
        $photo = $this->m->photos->first();
        $polish = $this->polish('ILNP', 'Aurora');
        auth()->logout();

        $this->actingAs($this->owner)->get($this->link($this->m).'/edit')->assertForbidden();
        $this->actingAs($this->owner)->put($this->link($this->m), ['worn_on' => '2026-10-16', 'caption' => 'Changed'])->assertForbidden();
        $this->actingAs($this->owner)->post("/manicure-photos/{$photo->id}/rotate", ['direction' => 'left'])->assertForbidden();
        $this->actingAs($this->owner)->delete("/manicure-photos/{$photo->id}")->assertForbidden();
        $this->actingAs($this->owner)->get($this->link($this->m).'/polishes')->assertForbidden();
        $this->actingAs($this->owner)->post($this->link($this->m).'/polishes', ['polish_id' => $polish->id])->assertForbidden();

        $fresh = $this->m->fresh();
        $this->assertSame('Autumn vibes', $fresh->caption);
        $this->assertSame(1, $fresh->photos()->count());
        $this->assertSame(0, $fresh->layers()->count());
    }

    public function test_after_a_restore_the_owner_can_edit_again(): void
    {
        $this->report($this->m);
        $this->hide();
        $this->actingAs($this->admin)->post("/admin/manicures/{$this->m->id}/restore");
        auth()->logout();

        $this->actingAs($this->owner)->put($this->link($this->m), ['worn_on' => '2026-10-16', 'caption' => 'Fixed'])->assertRedirect();

        $this->assertSame('Fixed', $this->m->fresh()->caption);
    }

    public function test_if_the_log_cannot_be_written_the_hide_does_not_happen(): void
    {
        $this->report($this->m);
        Event::listen('eloquent.creating: '.ModerationLog::class, fn () => throw new \RuntimeException('log unavailable'));

        $this->hide()->assertStatus(500);

        $this->assertNull($this->m->fresh()->hidden_at);
        $this->assertSame(1, Report::where('status', 'open')->count(), 'the reports stay open too');
    }

    public function test_if_the_log_cannot_be_written_the_delete_does_not_happen_and_no_file_is_lost(): void
    {
        $this->report($this->m);
        $path = $this->m->photos->first()->path;
        Event::listen('eloquent.creating: '.ModerationLog::class, fn () => throw new \RuntimeException('log unavailable'));

        $this->actingAs($this->admin)->delete("/admin/manicures/{$this->m->id}")->assertStatus(500);

        $this->assertNotNull(Manicure::find($this->m->id));
        Storage::disk('photos')->assertExists($path);
    }

    public function test_credit_links_are_only_rendered_for_https_urls(): void
    {
        $featured = $this->person('Featured');
        $m = $this->manicure($featured, [], 'public');
        $m->forceFill(['credit' => 'Photo by Someone, CC BY 4.0', 'credit_url' => 'javascript:alert(1)', 'license_url' => null])->save();

        foreach (['/manicures/'.$m->id, '/credits'] as $url) {
            $this->get($url)->assertOk()->assertSee('Photo by Someone')
                ->assertDontSee('javascript:', false)->assertDontSee('href=""', false);
        }
    }
}
