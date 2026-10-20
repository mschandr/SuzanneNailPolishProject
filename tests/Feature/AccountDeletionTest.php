<?php

namespace Tests\Feature;

use App\Jobs\DeleteStoredFiles;
use App\Mail\MagicLinkMail;
use App\Mail\NoAccountMail;
use App\Models\Bottle;
use App\Models\Brand;
use App\Models\Invite;
use App\Models\InviteRequest;
use App\Models\Location;
use App\Models\MagicLink;
use App\Models\Manicure;
use App\Models\ManicurePhoto;
use App\Models\ManicurePolish;
use App\Models\ModerationLog;
use App\Models\Polish;
use App\Models\Report;
use App\Models\User;
use App\Support\SelfSignup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\BuildsGalleryData;
use Tests\Support\TestImages;
use Tests\TestCase;

class AccountDeletionTest extends TestCase
{
    use BuildsGalleryData, RefreshDatabase;

    private User $me;

    private User $other;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('photos');
        Storage::fake('public');
        $this->me = User::factory()->create(['display_name' => 'Leaver', 'email' => 'leaver@example.com', 'signup_method' => 'self']);
        $this->other = User::factory()->create(['display_name' => 'Stayer', 'email' => 'stayer@example.com']);
    }

    /** Everything a User can own, created through the app so real files exist. Returns the ids for later checks. */
    private function populate(User $user): array
    {
        $brand = Brand::firstOrCreate(['name_key' => 'indie co'], ['name' => 'Indie Co']);
        $public = Polish::firstOrCreate(['brand_id' => $brand->id, 'name_key' => 'catalog shade'], ['name' => 'Catalog Shade']);

        // Private polish with a swatch photo on the public disk.
        $this->actingAs($user)->post('/my-polishes', ['brand' => 'Indie Co', 'name' => "Secret of {$user->display_name}", 'swatch' => UploadedFile::fake()->image('s.jpg')]);
        $private = Polish::where('user_id', $user->id)->firstOrFail();

        $location = $user->locations()->create(['name' => 'Cabinet']);
        $bottle = new Bottle(['polish_id' => $public->id, 'location_id' => $location->id]);
        $bottle->user_id = $user->id;
        $bottle->save();

        $response = $this->actingAs($user)->post('/manicures', [
            'worn_on' => '2026-10-15', 'caption' => "By {$user->display_name}", 'visibility' => 'public',
            'photos' => [TestImages::upload('a.jpg', 400, 300), TestImages::upload('b.jpg', 400, 300)],
        ]);
        $manicure = Manicure::findOrFail(basename($response->headers->get('Location')));
        $layer = new ManicurePolish;
        $layer->forceFill(['manicure_id' => $manicure->id, 'polish_id' => $public->id, 'bottle_id' => $bottle->id, 'position' => 0])->save();

        auth()->logout();

        return compact('private', 'location', 'bottle', 'manicure', 'public');
    }

    private function deleteAccount(User $as, string $confirm = 'DELETE')
    {
        return $this->actingAs($as)->post('/account/delete', ['confirmation' => $confirm]);
    }

    /** Row counts for every table: used to prove that deleting one account changes nothing else. */
    private function snapshot(?User $onlyOwnedBy = null): array
    {
        return [
            'users' => User::count(),
            'locations' => Location::where('user_id', $onlyOwnedBy->id)->count(),
            'bottles' => Bottle::where('user_id', $onlyOwnedBy->id)->count(),
            'manicures' => Manicure::where('user_id', $onlyOwnedBy->id)->count(),
            'photos' => ManicurePhoto::whereIn('manicure_id', Manicure::where('user_id', $onlyOwnedBy->id)->select('id'))->count(),
            'private polishes' => Polish::where('user_id', $onlyOwnedBy->id)->count(),
        ];
    }

    // --- the confirmation step ---------------------------------------------------------------

    public function test_a_guest_is_sent_to_sign_in(): void
    {
        $this->get('/account/delete')->assertRedirect('/login');
        $this->post('/account/delete', ['confirmation' => 'DELETE'])->assertRedirect('/login');
    }

    public function test_the_page_lists_what_will_be_deleted_with_counts(): void
    {
        $this->populate($this->me);

        $this->actingAs($this->me)->get('/account/delete')->assertOk()
            ->assertSee('permanent')
            ->assertSee('1 bottle')->assertSee('1 location')->assertSee('1 manicure')->assertSee('2 photos')->assertSee('1 private polish');
    }

    public function test_only_the_exact_word_confirms(): void
    {
        $this->populate($this->me);

        foreach (['', 'yes', 'delete my account', 'DELET', 'DELETE!'] as $wrong) {
            $this->deleteAccount($this->me, $wrong)->assertSessionHasErrors('confirmation');
        }
        $this->actingAs($this->me)->post('/account/delete', [])->assertSessionHasErrors('confirmation');

        $this->assertNotNull(User::find($this->me->id));
        $this->assertSame(1, Manicure::where('user_id', $this->me->id)->count());
    }

    public function test_the_word_may_be_typed_in_any_case_with_stray_spaces(): void
    {
        $this->deleteAccount($this->me, '  delete ')->assertRedirect();

        $this->assertNull(User::find($this->me->id));
    }

    // --- what gets deleted ------------------------------------------------------------------------

    public function test_everything_the_user_owns_is_gone_and_they_are_signed_out(): void
    {
        $data = $this->populate($this->me);

        $this->deleteAccount($this->me)->assertRedirect('/')->assertSessionHas('status');

        $this->assertNull(User::find($this->me->id), 'the user must not be re-created by the sign-out that follows');
        $this->assertGuest();
        $this->assertSame(0, Location::count());
        $this->assertSame(0, Bottle::count());
        $this->assertSame(0, Manicure::where('user_id', $this->me->id)->count());
        $this->assertSame(0, ManicurePhoto::count());
        $this->assertSame(0, ManicurePolish::count());
        $this->assertNull(Polish::find($data['private']->id));
        $this->assertNotNull(Polish::find($data['public']->id), 'catalog polishes are not theirs to delete');
    }

    public function test_other_users_data_is_completely_untouched(): void
    {
        $this->populate($this->me);
        $theirs = $this->populate($this->other);
        $before = $this->snapshot($this->other);

        $this->deleteAccount($this->me);

        $after = $this->snapshot($this->other);
        $this->assertSame($before['locations'], $after['locations']);
        $this->assertSame($before['bottles'], $after['bottles']);
        $this->assertSame($before['manicures'], $after['manicures']);
        $this->assertSame($before['photos'], $after['photos']);
        $this->assertSame($before['private polishes'], $after['private polishes']);
        $this->assertSame($before['users'] - 1, $after['users']);
        foreach ($theirs['manicure']->photos as $photo) {
            Storage::disk('photos')->assertExists($photo->path);
        }
        $this->assertNotNull(Bottle::find($theirs['bottle']->id));
        $this->assertSame($theirs['bottle']->id, ManicurePolish::firstOrFail()->bottle_id, 'their own layer link is intact');
    }

    public function test_hearts_given_and_received_both_go(): void
    {
        $data = $this->populate($this->me);
        $theirs = $this->populate($this->other);
        $this->heart($this->me, $theirs['manicure']);      // given by me
        $this->heart($this->other, $data['manicure']);     // received by me
        $this->heart($this->person('Third'), $theirs['manicure']);

        $this->deleteAccount($this->me);

        $this->assertSame(1, DB::table('hearts')->count(), 'only the third person\'s heart on the other user\'s manicure remains');
        $this->assertSame(1, DB::table('hearts')->where('manicure_id', $theirs['manicure']->id)->count());
    }

    public function test_reports_made_and_received_both_go(): void
    {
        $data = $this->populate($this->me);
        $theirs = $this->populate($this->other);
        foreach ([[$data['manicure'], $this->other], [$theirs['manicure'], $this->me]] as [$m, $by]) {
            $r = new Report(['reason' => 'spam']);
            $r->manicure_id = $m->id;
            $r->reporter_id = $by->id;
            $r->status = 'open';
            $r->save();
        }

        $this->deleteAccount($this->me);

        $this->assertSame(0, Report::count());
    }

    public function test_a_promoted_polish_stays_in_the_catalog_with_no_link_to_them(): void
    {
        $brand = Brand::create(['name' => 'Indie Co']);
        $promoted = new Polish(['brand_id' => $brand->id, 'name' => 'Promoted Gem']);
        $promoted->user_id = $this->me->id;
        $promoted->save();
        $admin = User::factory()->admin()->create(['display_name' => 'Boss']);
        $this->actingAs($this->me)->post("/my-polishes/{$promoted->id}/suggest");
        $this->actingAs($admin)->post("/admin/suggestions/{$promoted->id}/promote");
        $stillPrivate = new Polish(['brand_id' => $brand->id, 'name' => 'Never Suggested']);
        $stillPrivate->user_id = $this->me->id;
        $stillPrivate->save();
        auth()->logout();

        $this->deleteAccount($this->me);

        $fresh = Polish::find($promoted->id);
        $this->assertNotNull($fresh);
        $this->assertNull($fresh->user_id);
        $this->assertNull(Polish::find($stillPrivate->id));
        $this->get($fresh->fresh('brand')->url())->assertOk();
    }

    public function test_another_users_layer_that_used_their_private_polish_simply_disappears(): void
    {
        $data = $this->populate($this->me);
        $theirs = $this->populate($this->other);
        $layer = new ManicurePolish;
        $layer->forceFill(['manicure_id' => $theirs['manicure']->id, 'polish_id' => $data['private']->id, 'position' => 1])->save();

        $this->deleteAccount($this->me);

        $this->assertSame(0, ManicurePolish::where('polish_id', $data['private']->id)->count());
        $this->assertNotNull(Manicure::find($theirs['manicure']->id));
    }

    public function test_leftovers_tied_to_their_email_are_removed_but_other_emails_are_not(): void
    {
        $link = fn ($email) => MagicLink::create(['email' => $email, 'token_hash' => hash('sha256', $email), 'expires_at' => now()->addHour()]);
        $link('leaver@example.com');
        $link('stayer@example.com');
        $invite = fn ($email) => Invite::create(['email' => $email, 'token_hash' => hash('sha256', 'i'.$email), 'expires_at' => now()->addDay()]);
        $invite('leaver@example.com');
        $invite('stayer@example.com');
        InviteRequest::create(['email' => 'leaver@example.com']);
        InviteRequest::create(['email' => 'stayer@example.com']);
        DB::table('sessions')->insert([
            ['id' => 's1', 'user_id' => $this->me->id, 'payload' => '', 'last_activity' => time()],
            ['id' => 's2', 'user_id' => $this->other->id, 'payload' => '', 'last_activity' => time()],
        ]);

        $this->deleteAccount($this->me);

        $this->assertSame(['stayer@example.com'], MagicLink::pluck('email')->all());
        $this->assertSame(['stayer@example.com'], Invite::pluck('email')->all());
        $this->assertSame(['stayer@example.com'], InviteRequest::pluck('email')->all());
        $this->assertSame(['s2'], DB::table('sessions')->pluck('id')->all());
    }

    public function test_the_audit_log_keeps_its_history_but_forgets_the_admin(): void
    {
        $admin = User::factory()->admin()->create(['display_name' => 'Boss']);
        User::factory()->admin()->create(); // so Boss is not the last Admin
        ModerationLog::record($admin, 'suspend', 'user', $this->other->id, 'Suspended Stayer');

        $this->deleteAccount($admin)->assertRedirect('/');

        $entry = ModerationLog::firstOrFail();
        $this->assertSame('Suspended Stayer', $entry->summary);
        $this->assertNull($entry->admin_id);
    }

    // --- files ------------------------------------------------------------------------------------

    public function test_a_job_is_queued_with_exactly_their_files_after_the_rows_are_gone(): void
    {
        $data = $this->populate($this->me);
        $this->populate($this->other);
        $photoFiles = $data['manicure']->photos->flatMap(fn ($p) => [$p->path, $p->thumb_path])->all();
        $swatch = $data['private']->swatch_path;
        Queue::fake();

        $this->deleteAccount($this->me);

        Queue::assertPushed(DeleteStoredFiles::class, function ($job) use ($photoFiles, $swatch) {
            return $job->afterCommit
                && collect($job->files[config('nailpolish.photos_disk')] ?? [])->sort()->values()->all() === collect($photoFiles)->sort()->values()->all()
                && ($job->files['public'] ?? []) === [$swatch];
        });
        Queue::assertPushed(DeleteStoredFiles::class, 1);
    }

    public function test_running_the_job_removes_the_files_and_only_theirs(): void
    {
        $data = $this->populate($this->me);
        $theirs = $this->populate($this->other);
        $mine = $data['manicure']->photos->flatMap(fn ($p) => [$p->path, $p->thumb_path])->all();
        $swatch = $data['private']->swatch_path;
        foreach ([...$mine] as $f) {
            Storage::disk('photos')->assertExists($f);
        }
        Storage::disk('public')->assertExists($swatch);

        $this->deleteAccount($this->me); // the test queue runs jobs immediately

        foreach ($mine as $f) {
            Storage::disk('photos')->assertMissing($f);
        }
        Storage::disk('public')->assertMissing($swatch);
        foreach ($theirs['manicure']->photos as $p) {
            Storage::disk('photos')->assertExists($p->path);
            Storage::disk('photos')->assertExists($p->thumb_path);
        }
        Storage::disk('public')->assertExists($theirs['private']->swatch_path);
    }

    public function test_the_job_survives_files_that_are_already_gone(): void
    {
        $job = new DeleteStoredFiles(['photos' => ['manicures/never-existed.jpg'], 'public' => ['swatches/nope.jpg']]);

        $job->handle();

        $this->assertTrue(true, 'no exception');
    }

    public function test_the_job_is_retried_with_backoff_if_storage_is_down(): void
    {
        $job = new DeleteStoredFiles(['photos' => ['a.jpg']]);

        $this->assertGreaterThanOrEqual(3, $job->tries);
        $this->assertNotEmpty($job->backoff());
    }

    public function test_a_storage_failure_makes_the_job_fail_so_the_queue_retries_it(): void
    {
        Storage::shouldReceive('disk')->with('photos')->andReturn(new class
        {
            public function delete($paths)
            {
                throw new \RuntimeException('storage down');
            }
        });

        $this->expectException(\RuntimeException::class);

        (new DeleteStoredFiles(['photos' => ['a.jpg']]))->handle();
    }

    public function test_if_the_cleanup_cannot_even_be_queued_the_user_still_sees_success_and_the_paths_are_logged(): void
    {
        $this->populate($this->me);
        Log::spy();
        // The queue runs inline in tests, so a dead disk makes the dispatch itself throw.
        Storage::shouldReceive('disk')->andReturn(new class
        {
            public function delete($paths)
            {
                throw new \RuntimeException('storage down');
            }
        });

        $this->deleteAccount($this->me)->assertRedirect('/')->assertSessionHas('status');

        $this->assertNull(User::find($this->me->id));
        Log::shouldHaveReceived('error')->withArgs(fn ($message, $context = []) => str_contains($message, 'file cleanup') && ! empty($context['files']))->atLeast()->once();
    }

    public function test_emails_stored_in_another_case_are_still_cleaned_up(): void
    {
        DB::table('invite_requests')->insert(['id' => (string) Str::uuid(), 'email' => 'Leaver@Example.COM', 'created_at' => now(), 'updated_at' => now()]);

        $this->deleteAccount($this->me);

        $this->assertSame(0, InviteRequest::count());
    }

    public function test_two_admins_cannot_both_be_deleted(): void
    {
        $a = User::factory()->admin()->create();
        $b = User::factory()->admin()->create();

        $this->deleteAccount($a)->assertRedirect('/');
        auth()->logout();
        $this->deleteAccount($b)->assertSessionHasErrors('confirmation');

        $this->assertSame(1, User::where('is_admin', true)->count(), 'one Admin always remains');
    }

    public function test_the_account_is_deleted_even_if_the_file_cleanup_has_not_run_yet(): void
    {
        $data = $this->populate($this->me);
        $path = $data['manicure']->photos->first()->path; // read now: the row disappears with the account
        // A real worker handles the job later; until then (or if it fails and retries) the account is already gone.
        config(['queue.default' => 'database']);

        $this->deleteAccount($this->me)->assertRedirect('/');

        $this->assertNull(User::find($this->me->id));
        $this->assertSame(1, DB::table('jobs')->count(), 'the cleanup waits in the queue');
        Storage::disk('photos')->assertExists($path);
    }

    public function test_an_account_with_no_files_dispatches_nothing(): void
    {
        Queue::fake();

        $this->deleteAccount($this->me)->assertRedirect('/');

        Queue::assertNothingPushed();
    }

    // --- safeguards -----------------------------------------------------------------------------------

    public function test_the_last_admin_cannot_delete_their_account(): void
    {
        $admin = User::factory()->admin()->create();

        $this->deleteAccount($admin)->assertSessionHasErrors('confirmation');

        $this->assertNotNull(User::find($admin->id));
    }

    public function test_an_admin_can_delete_theirs_when_another_admin_exists(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->admin()->create();

        $this->deleteAccount($admin)->assertRedirect('/');

        $this->assertNull(User::find($admin->id));
        $this->assertSame(1, User::where('is_admin', true)->count());
    }

    public function test_the_delete_page_warns_the_last_admin_instead_of_offering_the_form(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/account/delete')->assertOk()->assertSee('last Admin');
    }

    public function test_deleting_an_account_does_not_free_up_a_self_signup_place(): void
    {
        config(['nailpolish.self_signup_limit' => 1]);
        $this->assertFalse(SelfSignup::isOpen(), 'the one self-signup place is used by Leaver');

        $this->deleteAccount($this->me);

        $this->assertFalse(SelfSignup::isOpen(), 'it stays closed after they leave');
    }

    public function test_the_email_can_sign_up_again_afterwards_only_by_invite_once_the_cap_is_reached(): void
    {
        config(['nailpolish.self_signup_limit' => 1]);
        $this->deleteAccount($this->me);
        Mail::fake();

        $this->post('/login', ['email' => 'leaver@example.com']);

        Mail::assertNotQueued(MagicLinkMail::class);
        Mail::assertQueued(NoAccountMail::class);
    }

    public function test_the_deleted_users_public_pages_no_longer_exist(): void
    {
        $data = $this->populate($this->me);
        $url = $this->link($data['manicure']);
        $this->get($url)->assertOk();

        $this->deleteAccount($this->me);
        auth()->logout();

        $this->get($url)->assertNotFound();
        $this->get("/people/{$this->me->id}")->assertNotFound();
        $this->get('/gallery')->assertDontSee($url, false);
    }

    public function test_the_page_is_reachable_from_help(): void
    {
        $this->actingAs($this->other)->get('/help')->assertSee('href="/account/delete"', false);
    }
}
