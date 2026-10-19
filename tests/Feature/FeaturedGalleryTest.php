<?php

namespace Tests\Feature;

use App\Models\Manicure;
use App\Models\User;
use Database\Seeders\FeaturedGallerySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FeaturedGalleryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('photos');
    }

    public function test_it_creates_one_featured_account_and_two_public_credited_manicures(): void
    {
        $this->seed(FeaturedGallerySeeder::class);

        $this->assertSame(1, User::where('display_name', 'Featured')->count());
        $manicures = Manicure::with('photos')->get();
        $this->assertCount(2, $manicures);
        foreach ($manicures as $m) {
            $this->assertTrue($m->isPublic());
            $this->assertNotEmpty($m->credit);
            $this->assertStringStartsWith('https://commons.wikimedia.org/wiki/File:', $m->credit_url);
            $this->assertStringStartsWith('https://creativecommons.org/licenses/', $m->license_url);
            $this->assertCount(1, $m->photos);
        }
    }

    public function test_the_credits_name_the_authors_and_licenses(): void
    {
        $this->seed(FeaturedGallerySeeder::class);

        $credits = Manicure::pluck('credit')->implode(' | ');
        $this->assertStringContainsString('Liekkikoira', $credits);
        $this->assertStringContainsString('CC BY-SA 4.0', $credits);
        $this->assertStringContainsString('Courtney Rhodes', $credits);
        $this->assertStringContainsString('CC BY 2.0', $credits);
    }

    public function test_running_it_twice_adds_nothing(): void
    {
        $this->seed(FeaturedGallerySeeder::class);
        $this->seed(FeaturedGallerySeeder::class);

        $this->assertSame(2, Manicure::count());
        $this->assertSame(1, User::where('display_name', 'Featured')->count());
        $this->assertCount(4, Storage::disk('photos')->allFiles()); // 2 photos x (full + thumb)
    }

    public function test_the_stored_photos_are_clean_jpegs_without_metadata(): void
    {
        $this->seed(FeaturedGallerySeeder::class);

        foreach (Manicure::with('photos')->get() as $m) {
            $bytes = Storage::disk('photos')->get($m->photos->first()->path);
            $this->assertNotFalse(imagecreatefromstring($bytes));
            $this->assertStringNotContainsString('Exif', $bytes);
            $this->assertLessThanOrEqual(1600, max(getimagesizefromstring($bytes)[0], getimagesizefromstring($bytes)[1]));
        }
    }

    public function test_they_appear_in_the_gallery_for_a_guest(): void
    {
        $this->seed(FeaturedGallerySeeder::class);

        $page = $this->get('/gallery')->assertOk()->assertSee('Featured');
        foreach (Manicure::all() as $m) {
            $page->assertSee("/manicures/{$m->id}", false);
        }
    }

    public function test_the_manicure_page_shows_the_credit_with_links_to_the_source_and_license(): void
    {
        $this->seed(FeaturedGallerySeeder::class);
        $m = Manicure::where('credit', 'like', '%Liekkikoira%')->firstOrFail();

        $this->get("/manicures/{$m->id}")->assertOk()
            ->assertSee('Liekkikoira')
            ->assertSee('CC BY-SA 4.0')
            ->assertSee('href="https://commons.wikimedia.org/wiki/File:Blue_cat_eye_nail_art.jpg"', false)
            ->assertSee('href="https://creativecommons.org/licenses/by-sa/4.0"', false);
    }

    public function test_an_ordinary_manicure_shows_no_credit_line(): void
    {
        $owner = User::factory()->create(['display_name' => 'Suzanne']);
        $m = new Manicure(['worn_on' => '2026-10-15', 'visibility' => 'public']);
        $m->user_id = $owner->id;
        $m->save();

        $this->get("/manicures/{$m->id}")->assertOk()->assertDontSee('Photo credit:');
    }

    public function test_the_credits_page_lists_every_credited_public_photo_without_signing_in(): void
    {
        $this->seed(FeaturedGallerySeeder::class);

        $this->get('/credits')->assertOk()
            ->assertSee('Liekkikoira')->assertSee('Courtney Rhodes')
            ->assertSee('href="https://creativecommons.org/licenses/by/2.0"', false);
    }

    public function test_a_credited_manicure_that_is_made_private_leaves_the_credits_page(): void
    {
        $this->seed(FeaturedGallerySeeder::class);
        Manicure::where('credit', 'like', '%Liekkikoira%')->update(['visibility' => 'private']);

        $this->get('/credits')->assertDontSee('Liekkikoira')->assertSee('Courtney Rhodes');
    }

    public function test_the_credits_page_is_linked_from_the_gallery(): void
    {
        $this->get('/gallery')->assertSee('href="/credits"', false);
    }

    public function test_the_featured_account_has_no_usable_email_and_is_not_counted_as_a_self_signup(): void
    {
        $this->seed(FeaturedGallerySeeder::class);

        $featured = User::where('display_name', 'Featured')->firstOrFail();
        $this->assertStringEndsWith('.invalid', $featured->email);
        $this->assertNotSame('self', $featured->signup_method);
        $this->get("/people/{$featured->id}")->assertOk()->assertDontSee($featured->email);
    }
}
