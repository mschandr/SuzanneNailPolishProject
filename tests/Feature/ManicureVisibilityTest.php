<?php

namespace Tests\Feature;

use App\Models\Manicure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\TestImages;
use Tests\TestCase;

class ManicureVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('photos');
        $this->owner = User::factory()->create(['display_name' => 'Suzanne', 'email' => 'suzanne-private@example.com']);
    }

    private function manicure(?string $visibility = null, ?User $owner = null, string $caption = 'Autumn vibes'): Manicure
    {
        $owner ??= $this->owner;
        $response = $this->actingAs($owner)->post('/manicures', [
            'worn_on' => '2026-10-15',
            'caption' => $caption,
            'visibility' => $visibility,
            'photos' => [TestImages::upload('a.jpg', 800, 600)],
        ]);
        auth()->logout();

        // The id comes from the redirect: two manicures made in one second tie on created_at.
        return Manicure::findOrFail(basename($response->headers->get('Location')));
    }

    public function test_a_public_manicure_is_visible_to_guests_and_strangers(): void
    {
        $m = $this->manicure('public');

        $this->get("/manicures/{$m->id}")->assertOk()->assertSee('Autumn vibes')->assertSee('Suzanne');
        $this->actingAs(User::factory()->create())->get("/manicures/{$m->id}")->assertOk();
    }

    public function test_the_page_never_shows_the_owners_email(): void
    {
        $m = $this->manicure('public');

        $this->get("/manicures/{$m->id}")->assertDontSee('suzanne-private@example.com');
    }

    public function test_a_private_manicure_is_visible_only_to_its_owner(): void
    {
        $m = $this->manicure('private');

        $this->actingAs($this->owner)->get("/manicures/{$m->id}")->assertOk()->assertSee('Autumn vibes');
        auth()->logout();
        $this->get("/manicures/{$m->id}")->assertNotFound();
        $this->actingAs(User::factory()->create())->get("/manicures/{$m->id}")->assertNotFound();
        $this->actingAs(User::factory()->admin()->create())->get("/manicures/{$m->id}")->assertNotFound();
    }

    public function test_a_private_manicure_answers_exactly_like_one_that_does_not_exist(): void
    {
        $m = $this->manicure('private');

        $missing = $this->get('/manicures/0198aaaa-0000-7000-8000-000000000000');
        $private = $this->get("/manicures/{$m->id}");

        $this->assertSame($missing->getStatusCode(), $private->getStatusCode());
        $private->assertDontSee('Autumn vibes');
    }

    public function test_following_the_account_default_a_manicure_is_public_until_the_default_changes(): void
    {
        $m = $this->manicure(null);
        $this->get("/manicures/{$m->id}")->assertOk();

        $this->owner->forceFill(['manicure_default_visibility' => 'private'])->save();

        $this->get("/manicures/{$m->id}")->assertNotFound();
    }

    public function test_changing_the_default_back_makes_it_visible_again_immediately(): void
    {
        $this->owner->forceFill(['manicure_default_visibility' => 'private'])->save();
        $m = $this->manicure(null);
        $this->get("/manicures/{$m->id}")->assertNotFound();

        $this->owner->forceFill(['manicure_default_visibility' => 'public'])->save();

        $this->get("/manicures/{$m->id}")->assertOk();
    }

    public function test_a_manicures_own_setting_beats_the_account_default(): void
    {
        $this->owner->forceFill(['manicure_default_visibility' => 'private'])->save();
        $stillPublic = $this->manicure('public', null, 'Override public');

        $this->owner->forceFill(['manicure_default_visibility' => 'public'])->save();
        $stillPrivate = $this->manicure('private', null, 'Override private');

        $this->get("/manicures/{$stillPublic->id}")->assertOk();
        $this->get("/manicures/{$stillPrivate->id}")->assertNotFound();
    }

    public function test_photos_follow_exactly_the_same_rules(): void
    {
        $public = $this->manicure('public');
        $private = $this->manicure('private', null, 'Hidden one');
        $publicPhoto = $public->photos->first();
        $privatePhoto = $private->photos->first();

        foreach (['full', 'thumb'] as $variant) {
            $this->get("/photos/{$publicPhoto->id}/{$variant}")->assertOk();
            $this->get("/photos/{$privatePhoto->id}/{$variant}")->assertNotFound();
            $this->actingAs(User::factory()->create())->get("/photos/{$privatePhoto->id}/{$variant}")->assertNotFound();
            $this->actingAs($this->owner)->get("/photos/{$privatePhoto->id}/{$variant}")->assertOk();
            auth()->logout();
        }
    }

    public function test_a_photo_stops_being_fetchable_the_moment_its_manicure_goes_private(): void
    {
        $m = $this->manicure('public');
        $photo = $m->photos->first();
        $this->get("/photos/{$photo->id}/full")->assertOk();

        $m->forceFill(['visibility' => 'private'])->save();

        $this->get("/photos/{$photo->id}/full")->assertNotFound();
    }

    public function test_photos_are_served_as_jpeg_with_safe_headers(): void
    {
        $photo = $this->manicure('public')->photos->first();

        $response = $this->get("/photos/{$photo->id}/full")->assertOk();

        $this->assertStringStartsWith('image/jpeg', $response->headers->get('Content-Type'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertNotFalse(imagecreatefromstring($response->streamedContent()));
    }

    public function test_cache_headers_never_let_a_shared_cache_keep_a_private_photo(): void
    {
        $public = $this->manicure('public')->photos->first();
        $private = $this->manicure('private')->photos->first();

        $publicCache = $this->get("/photos/{$public->id}/thumb")->headers->get('Cache-Control');
        $privateCache = $this->actingAs($this->owner)->get("/photos/{$private->id}/thumb")->headers->get('Cache-Control');

        $this->assertStringContainsString('public', $publicCache);
        $this->assertStringContainsString('max-age=300', $publicCache);
        $this->assertStringContainsString('private', $privateCache);
        $this->assertStringContainsString('no-store', $privateCache);
        $this->assertStringNotContainsString('public', $privateCache);
    }

    public function test_the_thumbnail_is_a_smaller_picture_than_the_full_photo(): void
    {
        $photo = $this->manicure('public')->photos->first();

        $full = $this->get("/photos/{$photo->id}/full")->streamedContent();
        $thumb = $this->get("/photos/{$photo->id}/thumb")->streamedContent();

        $this->assertSame(800, getimagesizefromstring($full)[0]);
        $this->assertSame(400, getimagesizefromstring($thumb)[0]);
        $this->assertLessThan(strlen($full), strlen($thumb));
    }

    public function test_an_unknown_variant_or_photo_is_404(): void
    {
        $photo = $this->manicure('public')->photos->first();

        $this->get("/photos/{$photo->id}/original")->assertNotFound();
        $this->get('/photos/0198aaaa-0000-7000-8000-000000000000/full')->assertNotFound();
        $this->get('/photos/not-a-uuid/full')->assertNotFound();
    }

    public function test_the_manicure_page_embeds_photos_through_the_authorized_route(): void
    {
        $m = $this->manicure('public');
        $photo = $m->photos->first();

        $html = $this->get("/manicures/{$m->id}")->getContent();

        $this->assertStringContainsString("/photos/{$photo->id}/full", $html);
        $this->assertStringNotContainsString($photo->path, $html, 'the storage path must never reach the browser');
    }

    public function test_the_default_visibility_setting_can_be_changed_and_only_by_its_owner(): void
    {
        $this->actingAs($this->owner)->put('/settings/manicures', ['manicure_default_visibility' => 'private'])
            ->assertRedirect('/settings/manicures');
        $this->assertSame('private', $this->owner->fresh()->manicure_default_visibility);

        $this->actingAs($this->owner)->put('/settings/manicures', ['manicure_default_visibility' => 'friends'])
            ->assertSessionHasErrors('manicure_default_visibility');
        $this->assertSame('private', $this->owner->fresh()->manicure_default_visibility);
    }

    public function test_the_settings_page_shows_the_current_choice_and_requires_sign_in(): void
    {
        $this->get('/settings/manicures')->assertRedirect('/login');

        $this->actingAs($this->owner)->get('/settings/manicures')->assertOk()->assertSee('Public');
    }
}
