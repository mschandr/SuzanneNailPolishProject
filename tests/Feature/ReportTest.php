<?php

namespace Tests\Feature;

use App\Models\Report;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\Support\BuildsGalleryData;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use BuildsGalleryData, RefreshDatabase;

    private User $owner;

    private User $reporter;

    private $manicure;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = $this->person('Suzanne');
        $this->reporter = $this->person('Reporter');
        $this->manicure = $this->manicure($this->owner, [], 'public');
    }

    private function report(array $data = [], ?User $as = null, $manicure = null)
    {
        $manicure ??= $this->manicure;

        return $this->actingAs($as ?? $this->reporter)->post("/manicures/{$manicure->id}/report", $data + ['reason' => 'spam']);
    }

    public function test_a_guest_is_sent_to_sign_in(): void
    {
        $this->post("/manicures/{$this->manicure->id}/report", ['reason' => 'spam'])->assertRedirect('/login');

        $this->assertSame(0, Report::count());
    }

    public function test_a_user_can_report_someone_elses_public_manicure(): void
    {
        $this->report(['reason' => 'inappropriate', 'details' => 'Not suitable'])->assertRedirect();

        $report = Report::firstOrFail();
        $this->assertSame($this->manicure->id, $report->manicure_id);
        $this->assertSame($this->reporter->id, $report->reporter_id);
        $this->assertSame('inappropriate', $report->reason);
        $this->assertSame('Not suitable', $report->details);
        $this->assertSame('open', $report->status);
    }

    public function test_the_user_is_thanked(): void
    {
        $this->report()->assertSessionHas('status', 'Thank you. The report has been sent to the Admin.');
    }

    public function test_details_are_optional(): void
    {
        $this->report(['details' => ''])->assertSessionHasNoErrors();

        $this->assertNull(Report::firstOrFail()->details);
    }

    public function test_every_listed_reason_is_accepted_and_others_are_refused(): void
    {
        foreach (array_keys(Report::REASONS) as $i => $reason) {
            $this->report(['reason' => $reason], $this->person("R{$i}"))->assertSessionHasNoErrors();
        }
        $this->assertSame(count(Report::REASONS), Report::count());

        $this->report(['reason' => 'because'])->assertSessionHasErrors('reason');
        $this->actingAs($this->reporter)->post("/manicures/{$this->manicure->id}/report", [])->assertSessionHasErrors('reason');
    }

    public function test_details_are_limited_to_1000_characters(): void
    {
        $this->report(['details' => str_repeat('x', 1001)])->assertSessionHasErrors('details');
    }

    public function test_you_cannot_report_your_own_manicure(): void
    {
        $this->report([], $this->owner)->assertSessionHasErrors('report');

        $this->assertSame(0, Report::count());
    }

    public function test_a_private_manicure_cannot_be_reported_and_answers_404(): void
    {
        $private = $this->manicure($this->owner, [], 'private');

        $this->report([], null, $private)->assertNotFound();

        $this->assertSame(0, Report::count());
    }

    public function test_an_unknown_manicure_is_404(): void
    {
        $this->actingAs($this->reporter)->post('/manicures/0198aaaa-0000-7000-8000-000000000000/report', ['reason' => 'spam'])->assertNotFound();
    }

    public function test_reporting_twice_keeps_one_report_and_says_so(): void
    {
        $this->report(['reason' => 'spam']);
        $this->report(['reason' => 'inappropriate'])->assertSessionHas('status', 'You have already reported this manicure.');

        $this->assertSame(1, Report::count());
        $this->assertSame('spam', Report::firstOrFail()->reason, 'the first report is kept as it was');
    }

    public function test_different_people_can_each_report_it(): void
    {
        $this->report([], $this->reporter);
        $this->report([], $this->person('Another'));

        $this->assertSame(2, Report::count());
    }

    public function test_the_reporter_cannot_set_the_status_or_identity(): void
    {
        $other = $this->person('Other');

        $this->report(['status' => 'dismissed', 'reporter_id' => $other->id, 'manicure_id' => $this->owner->id]);

        $report = Report::firstOrFail();
        $this->assertSame('open', $report->status);
        $this->assertSame($this->reporter->id, $report->reporter_id);
        $this->assertSame($this->manicure->id, $report->manicure_id);
    }

    public function test_reporting_is_rate_limited(): void
    {
        $last = null;
        foreach (range(1, 11) as $_) {
            $last = $this->report([], $this->reporter, $this->manicure($this->owner, [], 'public'));
        }

        $last->assertStatus(429);
        $this->assertSame(10, Report::count());
    }

    public function test_the_manicure_page_offers_the_report_form_to_others_but_not_the_owner_or_guests(): void
    {
        $url = "/manicures/{$this->manicure->id}";

        $this->actingAs($this->reporter)->get($url)->assertSee('Report this manicure')
            ->assertSee('action="/manicures/'.$this->manicure->id.'/report"', false);
        $this->actingAs($this->owner)->get($url)->assertDontSee('Report this manicure');
        auth()->logout();
        $this->get($url)->assertDontSee('Report this manicure')->assertSee('Sign in to report');
    }

    public function test_a_reporter_sees_that_they_have_already_reported(): void
    {
        $this->report();

        $this->actingAs($this->reporter)->get("/manicures/{$this->manicure->id}")
            ->assertSee('You reported this manicure')->assertDontSee('Report this manicure');
    }

    public function test_deleting_the_reporters_account_removes_their_reports(): void
    {
        $this->report();

        $this->reporter->delete();

        $this->assertSame(0, Report::count());
    }

    public function test_a_double_submit_race_is_a_friendly_repeat_not_a_500(): void
    {
        // Another request inserts the same report between our "already reported?" check and our insert.
        Event::listen('eloquent.creating: '.Report::class, function () {
            throw new UniqueConstraintViolationException('sqlite', 'insert', [], new \PDOException('UNIQUE constraint failed'));
        });

        $this->report()->assertRedirect()->assertSessionHas('status', 'You have already reported this manicure.');
    }
}
