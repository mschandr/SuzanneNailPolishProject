<?php

namespace Tests\Feature;

use App\Models\Manicure;
use App\Models\ModerationLog;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\BuildsGalleryData;
use Tests\TestCase;

class ModerationQueueTest extends TestCase
{
    use BuildsGalleryData, RefreshDatabase;

    protected User $admin;

    protected User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create(['display_name' => 'Boss']);
        $this->owner = $this->person('Suzanne');
    }

    protected function reportOn(Manicure $m, User $by, string $reason = 'spam', ?string $details = null, string $status = 'open'): Report
    {
        $r = new Report(['reason' => $reason, 'details' => $details]);
        $r->manicure_id = $m->id;
        $r->reporter_id = $by->id;
        $r->status = $status;
        $r->save();

        return $r;
    }

    public function test_only_the_admin_can_see_the_queue_and_the_log(): void
    {
        foreach (['/admin/moderation', '/admin/moderation/log'] as $url) {
            $this->get($url)->assertRedirect('/login');
            $this->actingAs($this->person('Plain'))->get($url)->assertForbidden();
            auth()->logout();
            $this->actingAs($this->admin)->get($url)->assertOk();
            auth()->logout();
        }
    }

    public function test_the_queue_groups_open_reports_by_manicure_with_reasons_and_details(): void
    {
        $m = $this->manicure($this->owner, [], 'public', '2026-10-15', 'Bad caption');
        $this->reportOn($m, $this->person('R1'), 'spam', 'Selling stuff');
        $this->reportOn($m, $this->person('R2'), 'inappropriate', 'Offensive text');

        $page = $this->actingAs($this->admin)->get('/admin/moderation')->assertOk();

        $page->assertSee('Suzanne')->assertSee('2 reports')
            ->assertSee('Spam or advertising')->assertSee('Selling stuff')
            ->assertSee('Inappropriate or offensive')->assertSee('Offensive text')
            ->assertSee($this->link($m), false);
        $this->assertSame(1, substr_count($page->getContent(), 'action="/admin/reports/'.$m->id.'/dismiss"'));
    }

    public function test_the_queue_shows_the_manicure_with_the_most_reports_first(): void
    {
        $few = $this->manicure($this->owner, [], 'public', '2026-10-15', 'few');
        $many = $this->manicure($this->owner, [], 'public', '2026-10-14', 'many');
        $this->reportOn($few, $this->person('A'));
        foreach (['B', 'C', 'D'] as $n) {
            $this->reportOn($many, $this->person($n));
        }

        $this->actingAs($this->admin)->get('/admin/moderation')->assertSeeInOrder([$this->link($many), $this->link($few)], false);
    }

    public function test_handled_reports_are_not_in_the_queue(): void
    {
        $m = $this->manicure($this->owner, [], 'public');
        $this->reportOn($m, $this->person('A'), 'spam', 'old news', 'dismissed');
        $this->reportOn($m, $this->person('B'), 'spam', 'also old', 'actioned');

        $this->actingAs($this->admin)->get('/admin/moderation')->assertDontSee('old news')->assertDontSee('also old')->assertSee('No open reports');
    }

    public function test_the_queue_does_not_expose_reporters_emails(): void
    {
        $m = $this->manicure($this->owner, [], 'public');
        $reporter = User::factory()->create(['display_name' => 'Rita', 'email' => 'rita-secret@example.com']);
        $this->reportOn($m, $reporter);

        $this->actingAs($this->admin)->get('/admin/moderation')->assertSee('Rita')->assertDontSee('rita-secret@example.com');
    }

    public function test_the_admin_can_open_a_reported_manicure_even_if_it_has_since_gone_private(): void
    {
        $m = $this->manicure($this->owner, [], 'public');
        $this->reportOn($m, $this->person('A'));
        $m->forceFill(['visibility' => 'private'])->save();

        $this->actingAs($this->admin)->get($this->link($m))->assertOk();
    }

    public function test_the_admin_still_cannot_open_an_unreported_private_manicure(): void
    {
        $m = $this->manicure($this->owner, [], 'private');

        $this->actingAs($this->admin)->get($this->link($m))->assertNotFound();
    }

    public function test_dismissing_closes_all_open_reports_and_leaves_the_manicure_alone(): void
    {
        $m = $this->manicure($this->owner, [], 'public');
        $this->reportOn($m, $this->person('A'));
        $this->reportOn($m, $this->person('B'));

        $this->actingAs($this->admin)->post("/admin/reports/{$m->id}/dismiss")->assertRedirect('/admin/moderation');

        $this->assertSame(0, Report::where('status', 'open')->count());
        $this->assertSame(2, Report::where('status', 'dismissed')->whereNotNull('handled_at')->count());
        $this->assertNull($m->fresh()->hidden_at);
        $this->get('/gallery')->assertSee($this->link($m), false);
    }

    public function test_dismissing_is_logged(): void
    {
        $m = $this->manicure($this->owner, [], 'public', '2026-10-15', 'A caption');
        $this->reportOn($m, $this->person('A'));

        $this->actingAs($this->admin)->post("/admin/reports/{$m->id}/dismiss");

        $entry = ModerationLog::firstOrFail();
        $this->assertSame('dismiss_reports', $entry->action);
        $this->assertSame($this->admin->id, $entry->admin_id);
        $this->assertSame($m->id, $entry->target_id);
        $this->assertStringContainsString('Suzanne', $entry->summary);
    }

    public function test_dismissing_with_nothing_open_is_404(): void
    {
        $m = $this->manicure($this->owner, [], 'public');

        $this->actingAs($this->admin)->post("/admin/reports/{$m->id}/dismiss")->assertNotFound();
        $this->assertSame(0, ModerationLog::count());
    }

    public function test_a_new_report_after_a_dismissal_reopens_the_question(): void
    {
        $m = $this->manicure($this->owner, [], 'public');
        $this->reportOn($m, $this->person('A'));
        $this->actingAs($this->admin)->post("/admin/reports/{$m->id}/dismiss");
        auth()->logout();

        $this->actingAs($this->person('Later'))->post("/manicures/{$m->id}/report", ['reason' => 'spam']);
        auth()->logout();

        $this->actingAs($this->admin)->get('/admin/moderation')->assertSee($this->link($m), false)->assertSee('1 report');
    }

    public function test_non_admins_cannot_dismiss(): void
    {
        $m = $this->manicure($this->owner, [], 'public');
        $this->reportOn($m, $this->person('A'));

        $this->actingAs($this->person('Plain'))->post("/admin/reports/{$m->id}/dismiss")->assertForbidden();

        $this->assertSame(1, Report::where('status', 'open')->count());
    }

    public function test_the_log_lists_entries_newest_first_with_who_and_what(): void
    {
        ModerationLog::record($this->admin, 'hide', 'manicure', null, 'Hid manicure by Suzanne', 'rule 3');
        $this->travel(5)->minutes();
        ModerationLog::record($this->admin, 'suspend', 'user', $this->owner->id, 'Suspended Suzanne');

        $this->actingAs($this->admin)->get('/admin/moderation/log')->assertOk()
            ->assertSeeInOrder(['Suspended Suzanne', 'Hid manicure by Suzanne'])
            ->assertSee('Boss')->assertSee('rule 3');
    }

    public function test_a_dismissed_report_does_not_leave_the_admin_with_standing_access(): void
    {
        $m = $this->manicure($this->owner, [], 'public');
        $this->reportOn($m, $this->person('A'));
        $this->actingAs($this->admin)->post("/admin/reports/{$m->id}/dismiss");
        $m->forceFill(['visibility' => 'private'])->save();

        // The owner made it private after the Admin cleared it: that is private again, even to the Admin.
        $this->actingAs($this->admin)->get($this->link($m))->assertNotFound();
        $this->actingAs($this->admin)->post("/admin/manicures/{$m->id}/hide")->assertNotFound();
        $this->actingAs($this->admin)->delete("/admin/manicures/{$m->id}")->assertNotFound();
    }

    public function test_the_admin_cannot_heart_or_report_a_manicure_they_can_only_see_for_review(): void
    {
        $m = $this->manicure($this->owner, [], 'public');
        $this->reportOn($m, $this->person('A'));
        $m->forceFill(['visibility' => 'private'])->save();

        $this->actingAs($this->admin)->get($this->link($m))->assertOk(); // reviewable
        $this->actingAs($this->admin)->post($this->link($m).'/heart')->assertNotFound();
        $this->actingAs($this->admin)->post($this->link($m).'/report', ['reason' => 'spam'])->assertNotFound();

        $this->assertSame(0, DB::table('hearts')->count());
        $this->assertSame(1, Report::count());
    }
}
