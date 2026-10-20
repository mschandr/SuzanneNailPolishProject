<?php

namespace Tests\Feature;

use App\Models\Manicure;
use App\Models\ManicurePhoto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\TestImages;
use Tests\TestCase;

class ManicureCreateTest extends TestCase
{
    use RefreshDatabase;

    private User $me;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('photos');
        $this->me = User::factory()->create();
    }

    private function create(array $data = [])
    {
        return $this->actingAs($this->me)->post('/manicures', $data + ['worn_on' => '2026-10-17']);
    }

    private function stored(ManicurePhoto $photo, bool $thumb = false): string
    {
        return Storage::disk('photos')->get($thumb ? $photo->thumb_path : $photo->path);
    }

    public function test_a_guest_is_sent_to_sign_in(): void
    {
        $this->get('/manicures/create')->assertRedirect('/login');
        $this->post('/manicures', [])->assertRedirect('/login');
    }

    public function test_a_manicure_is_created_with_a_photo_a_date_and_a_caption(): void
    {
        $this->create([
            'photos' => [TestImages::upload('a.jpg', 800, 600)],
            'caption' => 'Autumn vibes',
            'worn_on' => '2026-10-15',
        ])->assertRedirect();

        $manicure = Manicure::firstOrFail();
        $this->assertSame($this->me->id, $manicure->user_id);
        $this->assertSame('Autumn vibes', $manicure->caption);
        $this->assertSame('2026-10-15', $manicure->worn_on->toDateString());
        $this->assertSame(1, $manicure->photos()->count());
    }

    public function test_at_least_one_photo_is_required(): void
    {
        $this->create(['photos' => []])->assertSessionHasErrors('photos');
        $this->create()->assertSessionHasErrors('photos');

        $this->assertSame(0, Manicure::count());
    }

    public function test_up_to_four_photos_are_accepted_and_five_are_refused(): void
    {
        $four = array_map(fn ($i) => TestImages::upload("p{$i}.jpg", 400, 300), range(1, 4));
        $this->create(['photos' => $four])->assertSessionHasNoErrors();
        $this->assertSame(4, ManicurePhoto::count());

        $five = array_map(fn ($i) => TestImages::upload("q{$i}.jpg", 400, 300), range(1, 5));
        $this->create(['photos' => $five])->assertSessionHasErrors('photos');
        $this->assertSame(1, Manicure::count());
    }

    public function test_photos_keep_the_order_they_were_uploaded_in(): void
    {
        $this->create(['photos' => [
            TestImages::upload('first.jpg', 400, 300),
            TestImages::upload('second.jpg', 500, 300),
            TestImages::upload('third.jpg', 600, 300),
        ]]);

        $widths = Manicure::firstOrFail()->photos->map(fn ($p) => getimagesizefromstring($this->stored($p))[0])->all();
        $this->assertSame([400, 500, 600], $widths);
    }

    public function test_a_non_image_is_refused(): void
    {
        $this->create(['photos' => [UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf')]])
            ->assertSessionHasErrors('photos.0');

        $this->assertSame(0, Manicure::count());
    }

    public function test_a_file_pretending_to_be_an_image_is_refused(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'fake');
        file_put_contents($path, '<?php echo "not an image";');
        $fake = new UploadedFile($path, 'evil.jpg', 'image/jpeg', null, true);

        $this->create(['photos' => [$fake]])->assertSessionHasErrors('photos.0');

        $this->assertSame(0, ManicurePhoto::count());
    }

    public function test_a_photo_over_10_megabytes_is_refused(): void
    {
        $big = UploadedFile::fake()->image('big.jpg', 100, 100)->size(10241);

        $this->create(['photos' => [$big]])->assertSessionHasErrors('photos.0');
    }

    public function test_an_absurd_pixel_count_is_refused_before_it_can_exhaust_memory(): void
    {
        // A small PNG header claiming 20000x20000: decoding it would need ~1.6 GB.
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==');
        $png = substr($png, 0, 16).pack('N', 20000).pack('N', 20000).substr($png, 24);
        $path = tempnam(sys_get_temp_dir(), 'png');
        file_put_contents($path, $png);

        $this->create(['photos' => [new UploadedFile($path, 'huge.png', 'image/png', null, true)]])
            ->assertSessionHasErrors('photos.0');

        $this->assertSame(0, Manicure::count());
    }

    public function test_exif_metadata_is_stripped_from_the_stored_photo_and_its_thumbnail(): void
    {
        $upload = TestImages::upload('phone.jpg', 800, 600, 1);
        $this->assertStringContainsString(TestImages::SECRET_CAMERA, file_get_contents($upload->getPathname()));

        $this->create(['photos' => [$upload]]);

        $photo = Manicure::firstOrFail()->photos->first();
        foreach ([false, true] as $thumb) {
            $bytes = $this->stored($photo, $thumb);
            $this->assertStringNotContainsString(TestImages::SECRET_CAMERA, $bytes);
            $this->assertStringNotContainsString('Exif', $bytes);
            $this->assertNotFalse(imagecreatefromstring($bytes), 'still a valid image');
        }
    }

    public function test_phone_orientation_is_applied_before_the_metadata_is_dropped(): void
    {
        // Stored 800x600 with orientation 6 = "rotate 90 clockwise to display": shown, it is 600x800.
        $this->create(['photos' => [TestImages::upload('phone.jpg', 800, 600, 6)]]);

        [$w, $h] = getimagesizefromstring($this->stored(Manicure::firstOrFail()->photos->first()));

        $this->assertSame([600, 800], [$w, $h]);
    }

    public function test_a_large_photo_is_scaled_down_to_1600_and_a_thumbnail_to_400(): void
    {
        $this->create(['photos' => [TestImages::upload('big.jpg', 3200, 2400)]]);

        $photo = Manicure::firstOrFail()->photos->first();
        $this->assertSame([1600, 1200], array_slice(getimagesizefromstring($this->stored($photo)), 0, 2));
        $this->assertSame([400, 300], array_slice(getimagesizefromstring($this->stored($photo, true)), 0, 2));
    }

    public function test_a_small_photo_is_never_enlarged(): void
    {
        $this->create(['photos' => [TestImages::upload('small.jpg', 300, 200)]]);

        $photo = Manicure::firstOrFail()->photos->first();
        $this->assertSame([300, 200], array_slice(getimagesizefromstring($this->stored($photo)), 0, 2));
        $this->assertSame([300, 200], array_slice(getimagesizefromstring($this->stored($photo, true)), 0, 2));
    }

    public function test_png_and_webp_uploads_are_accepted(): void
    {
        $png = UploadedFile::fake()->image('a.png', 200, 200);
        $webp = UploadedFile::fake()->image('b.webp', 200, 200);

        $this->create(['photos' => [$png, $webp]])->assertSessionHasNoErrors();

        $this->assertSame(2, ManicurePhoto::count());
    }

    public function test_the_original_upload_name_never_appears_in_the_stored_path(): void
    {
        $this->create(['photos' => [TestImages::upload('my-secret-name.jpg', 300, 200)]]);

        $photo = ManicurePhoto::firstOrFail();
        $this->assertStringNotContainsString('secret', $photo->path.$photo->thumb_path);
    }

    public function test_visibility_defaults_to_following_the_account_and_can_be_set(): void
    {
        $this->create(['photos' => [TestImages::upload('a.jpg', 300, 200)]]);
        $this->create(['photos' => [TestImages::upload('b.jpg', 300, 200)], 'visibility' => 'private']);
        $this->create(['photos' => [TestImages::upload('c.jpg', 300, 200)], 'visibility' => 'public']);

        $this->assertSame([null, 'private', 'public'], Manicure::orderBy('created_at')->pluck('visibility')->all());
    }

    public function test_an_unknown_visibility_is_refused(): void
    {
        $this->create(['photos' => [TestImages::upload('a.jpg', 300, 200)], 'visibility' => 'friends'])
            ->assertSessionHasErrors('visibility');
    }

    public function test_the_owner_cannot_be_chosen_through_the_request(): void
    {
        $other = User::factory()->create();

        $this->create(['photos' => [TestImages::upload('a.jpg', 300, 200)], 'user_id' => $other->id]);

        $this->assertSame($this->me->id, Manicure::firstOrFail()->user_id);
    }

    public function test_a_date_is_required_and_cannot_be_in_the_far_future(): void
    {
        $this->actingAs($this->me)->post('/manicures', ['photos' => [TestImages::upload('a.jpg', 300, 200)]])
            ->assertSessionHasErrors('worn_on');
        $this->create(['photos' => [TestImages::upload('a.jpg', 300, 200)], 'worn_on' => now()->addYear()->toDateString()])
            ->assertSessionHasErrors('worn_on');
    }

    public function test_the_caption_is_limited_to_500_characters(): void
    {
        $this->create(['photos' => [TestImages::upload('a.jpg', 300, 200)], 'caption' => str_repeat('x', 501)])
            ->assertSessionHasErrors('caption');
    }

    /** Where the black (originally top-left) and red (originally top-right) squares must end up. */
    public static function orientations(): array
    {
        return [
            'normal' => [1, false, 'top-left', 'top-right'],
            'mirrored horizontally' => [2, false, 'top-right', 'top-left'],
            'upside down' => [3, false, 'bottom-right', 'bottom-left'],
            'mirrored vertically' => [4, false, 'bottom-left', 'bottom-right'],
            'transposed' => [5, true, 'top-left', 'bottom-left'],
            'rotated 90 clockwise' => [6, true, 'top-right', 'bottom-right'],
            'transversed' => [7, true, 'bottom-right', 'top-right'],
            'rotated 90 counter-clockwise' => [8, true, 'bottom-left', 'top-left'],
        ];
    }

    #[DataProvider('orientations')]
    public function test_every_exif_orientation_is_applied_to_the_right_corners(int $orientation, bool $swapsSides, string $blackAt, string $redAt): void
    {
        $this->create(['photos' => [TestImages::upload('phone.jpg', 800, 600, $orientation)]]);

        $im = imagecreatefromstring($this->stored(Manicure::firstOrFail()->photos->first()));
        [$w, $h] = [imagesx($im), imagesy($im)];
        $this->assertSame($swapsSides ? [600, 800] : [800, 600], [$w, $h]);

        $at = function (string $corner) use ($im, $w, $h) {
            $rgb = imagecolorat($im, str_contains($corner, 'right') ? $w - 5 : 5, str_contains($corner, 'bottom') ? $h - 5 : 5);

            return [($rgb >> 16) & 255, ($rgb >> 8) & 255, $rgb & 255];
        };
        $this->assertLessThan(80, max($at($blackAt)), "black square should be at {$blackAt}");
        $c = $at($redAt);
        $this->assertTrue($c[0] > 180 && $c[1] < 90 && $c[2] < 90, "red square should be at {$redAt}");
    }

    public function test_a_large_rotated_photo_is_oriented_and_scaled_correctly(): void
    {
        // 3200x2400 stored with orientation 6: displayed as 2400x3200, then scaled so the long edge is 1600.
        $this->create(['photos' => [TestImages::upload('big.jpg', 3200, 2400, 6)]]);

        $photo = Manicure::firstOrFail()->photos->first();
        $this->assertSame([1200, 1600], array_slice(getimagesizefromstring($this->stored($photo)), 0, 2));
        $this->assertSame([300, 400], array_slice(getimagesizefromstring($this->stored($photo, true)), 0, 2));
    }

    public function test_an_image_that_looks_valid_but_cannot_be_decoded_gives_a_friendly_error_not_a_500(): void
    {
        // A real JPEG header (so size detection passes) with the picture data cut off.
        $path = tempnam(sys_get_temp_dir(), 'trunc');
        file_put_contents($path, substr(TestImages::jpeg(800, 600), 0, 330));
        $broken = new UploadedFile($path, 'broken.jpg', 'image/jpeg', null, true);

        $this->create(['photos' => [$broken]])->assertSessionHasErrors('photos');

        $this->assertSame(0, Manicure::count());
        $this->assertSame([], Storage::disk('photos')->allFiles());
    }

    public function test_the_too_many_photos_message_says_how_many_more_fit(): void
    {
        $this->create(['photos' => array_map(fn ($i) => TestImages::upload("p{$i}.jpg", 300, 200), range(1, 5))])
            ->assertSessionHasErrors(['photos' => 'You can have at most 4 photos on a manicure.']);
    }

    public function test_the_upload_errors_are_written_for_people_not_programmers(): void
    {
        $big = UploadedFile::fake()->image('big.jpg', 100, 100)->size(10241);
        $pdf = UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf');

        $this->create(['photos' => [$big]])
            ->assertSessionHasErrors(['photos.0' => 'Each photo must be 10 MB or smaller.']);
        $this->create(['photos' => [$pdf]])
            ->assertSessionHasErrors(['photos.0' => 'Photos must be JPG, PNG or WebP pictures.']);
    }

    public function test_a_photo_that_failed_to_upload_gets_a_plain_message(): void
    {
        // A file the server rejected before Laravel saw it (for instance one over PHP's own upload limit).
        $failed = new UploadedFile(tempnam(sys_get_temp_dir(), 'x'), 'photo.jpg', 'image/jpeg', UPLOAD_ERR_INI_SIZE, true);

        $this->create(['photos' => [$failed]])
            ->assertSessionHasErrors(['photos.0' => 'That photo could not be uploaded. It may be over 10 MB, or the connection dropped. Please try again.']);
    }

    public function test_the_same_friendly_errors_apply_when_adding_photos_to_an_existing_manicure(): void
    {
        $this->create(['photos' => [TestImages::upload('a.jpg', 300, 200)]]);
        $m = Manicure::firstOrFail();
        $big = UploadedFile::fake()->image('big.jpg', 100, 100)->size(10241);

        $this->actingAs($this->me)->put("/manicures/{$m->id}", ['worn_on' => '2026-10-15', 'photos' => [$big]])
            ->assertSessionHasErrors(['photos.0' => 'Each photo must be 10 MB or smaller.']);
    }
}
