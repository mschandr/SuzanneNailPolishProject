<?php

namespace Tests\Feature;

use App\Models\Manicure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\BuildsGalleryData;
use Tests\Support\TestImages;
use Tests\TestCase;

class RateLimitAndPhotoCookieTest extends TestCase
{
    use BuildsGalleryData, RefreshDatabase;

    public function test_the_load_test_switch_is_off_by_default(): void
    {
        $this->assertFalse(config('nailpolish.load_test_mode'));
    }

    public function test_sign_in_link_attempts_are_limited_per_ip_by_default(): void
    {
        $last = null;
        foreach (range(1, 21) as $_) {
            $last = $this->get('/login/not-a-real-token');
        }

        $last->assertStatus(429);
    }

    public function test_load_test_mode_lifts_the_limits_so_one_test_machine_can_act_as_many_users(): void
    {
        config(['nailpolish.load_test_mode' => true]);

        foreach (range(1, 60) as $_) {
            $this->get('/login/not-a-real-token')->assertStatus(200); // the confirm page: never throttled
        }
    }

    public function test_the_per_user_limits_for_hearts_and_reports_still_hold_by_default(): void
    {
        $owner = $this->person('Owner');
        $fan = $this->person('Fan');
        $m = $this->manicure($owner, [], 'public');

        $last = null;
        foreach (range(1, 61) as $_) {
            $last = $this->actingAs($fan)->post("/manicures/{$m->id}/heart");
        }
        $last->assertStatus(429);
    }

    public function test_hearts_and_reports_are_limited_independently_per_user(): void
    {
        $owner = $this->person('Owner');
        $m = $this->manicure($owner, [], 'public');

        foreach (range(1, 60) as $_) {
            $this->actingAs($this->person('Fan'))->post("/manicures/{$m->id}/heart");
        }
        // A different user is unaffected by the first one's count, so the 61st request overall succeeds.
        $this->actingAs($this->person('Another'))->post("/manicures/{$m->id}/heart")->assertStatus(302);
    }

    // --- public photos must be cacheable by nginx: no cookies on them -------------------------------

    private function photoFor(string $visibility): array
    {
        Storage::fake('photos');
        $owner = $this->person('Owner');
        $response = $this->actingAs($owner)->post('/manicures', [
            'worn_on' => '2026-10-15', 'visibility' => $visibility, 'photos' => [TestImages::upload('a.jpg', 400, 300)],
        ]);
        auth()->logout();
        $manicure = Manicure::findOrFail(basename($response->headers->get('Location')));

        return [$owner, $manicure->photos->first()];
    }

    public function test_a_public_photo_response_sets_no_cookies_so_a_shared_cache_may_keep_it(): void
    {
        [, $photo] = $this->photoFor('public');

        $response = $this->get("/photos/{$photo->id}/thumb")->assertOk();

        $this->assertSame([], $response->headers->getCookies(), 'no session or CSRF cookie on a public photo');
        $this->assertStringContainsString('public', $response->headers->get('Cache-Control'));
    }

    public function test_a_public_photo_still_sets_no_cookie_for_a_signed_in_viewer(): void
    {
        [, $photo] = $this->photoFor('public');

        $response = $this->actingAs($this->person('Viewer'))->get("/photos/{$photo->id}/full")->assertOk();

        $this->assertSame([], $response->headers->getCookies());
    }

    public function test_a_private_photo_for_its_owner_is_not_marked_public_at_all(): void
    {
        [$owner, $photo] = $this->photoFor('private');

        $response = $this->actingAs($owner)->get("/photos/{$photo->id}/thumb")->assertOk();

        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertStringNotContainsString('public', $response->headers->get('Cache-Control'));
    }

    public function test_ordinary_pages_still_get_their_session_cookie(): void
    {
        $response = $this->get('/gallery')->assertOk();

        $this->assertNotEmpty($response->headers->getCookies());
    }

    public function test_the_404_for_a_hidden_photo_is_not_cacheable_by_a_shared_cache(): void
    {
        [, $photo] = $this->photoFor('private');

        $response = $this->get("/photos/{$photo->id}/thumb")->assertNotFound();

        $this->assertStringNotContainsString('public', (string) $response->headers->get('Cache-Control'));
    }

    public function test_hearting_and_unhearting_share_one_budget(): void
    {
        $owner = $this->person('Owner');
        $fan = $this->person('Fan');
        $m = $this->manicure($owner, [], 'public');

        // 30 hearts + 30 un-hearts = 60 requests: all allowed. The 61st, of either kind, is refused.
        foreach (range(1, 30) as $_) {
            $this->actingAs($fan)->post("/manicures/{$m->id}/heart")->assertStatus(302);
            $this->actingAs($fan)->delete("/manicures/{$m->id}/heart")->assertStatus(302);
        }

        $this->actingAs($fan)->delete("/manicures/{$m->id}/heart")->assertStatus(429);
        $this->actingAs($fan)->post("/manicures/{$m->id}/heart")->assertStatus(429);
    }

    public function test_the_health_page_says_so_if_the_rate_limits_are_switched_off(): void
    {
        $this->getJson('/health')->assertExactJson(['app' => 'ok', 'database' => 'ok', 'queue' => 'ok']);

        config(['nailpolish.load_test_mode' => true]);

        $this->getJson('/health')->assertOk()->assertJsonPath('warning', 'LOAD_TEST_MODE is on: rate limits are disabled. Never leave this on in production.');
    }
}
