<?php

namespace Tests\Feature;

use App\Models\Manicure;
use App\Models\ManicurePhoto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\TestImages;
use Tests\TestCase;

class ManicureManageTest extends TestCase
{
    use RefreshDatabase;

    private User $me;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('photos');
        $this->me = User::factory()->create();
    }

    private function make(int $photos = 1, ?User $owner = null): Manicure
    {
        $owner ??= $this->me;
        $response = $this->actingAs($owner)->post('/manicures', [
            'worn_on' => '2026-10-15',
            'caption' => 'Original caption',
            'photos' => array_map(fn ($i) => TestImages::upload("p{$i}.jpg", 800, 600), range(1, $photos)),
        ]);
        auth()->logout();

        return Manicure::findOrFail(basename($response->headers->get('Location')));
    }

    private function imageSize(ManicurePhoto $photo, bool $thumb = false): array
    {
        $bytes = Storage::disk('photos')->get($thumb ? $photo->fresh()->thumb_path : $photo->fresh()->path);

        return array_slice(getimagesizefromstring($bytes), 0, 2);
    }

    /** Colour of a pixel as [r, g, b], read from the stored full-size photo. */
    private function pixel(ManicurePhoto $photo, string $corner): array
    {
        $im = imagecreatefromstring(Storage::disk('photos')->get($photo->fresh()->path));
        $x = str_contains($corner, 'right') ? imagesx($im) - 5 : 5;
        $y = str_contains($corner, 'bottom') ? imagesy($im) - 5 : 5;
        $rgb = imagecolorat($im, $x, $y);

        return [($rgb >> 16) & 255, ($rgb >> 8) & 255, $rgb & 255];
    }

    private function isRed(array $c): bool
    {
        return $c[0] > 180 && $c[1] < 90 && $c[2] < 90;
    }

    private function isBlack(array $c): bool
    {
        return max($c) < 80;
    }

    public function test_only_the_owner_can_open_edit_or_change_anything(): void
    {
        $m = $this->make();
        $photo = $m->photos->first();
        $stranger = User::factory()->create();

        $this->actingAs($stranger)->get("/manicures/{$m->id}/edit")->assertNotFound();
        $this->actingAs($stranger)->put("/manicures/{$m->id}", ['worn_on' => '2026-10-16'])->assertNotFound();
        $this->actingAs($stranger)->delete("/manicures/{$m->id}")->assertNotFound();
        $this->actingAs($stranger)->post("/manicure-photos/{$photo->id}/rotate", ['direction' => 'right'])->assertNotFound();
        $this->actingAs($stranger)->delete("/manicure-photos/{$photo->id}")->assertNotFound();

        $this->assertSame('Original caption', $m->fresh()->caption);
        $this->assertNotNull(Manicure::find($m->id));
    }

    public function test_a_guest_is_sent_to_sign_in(): void
    {
        $m = $this->make();

        $this->get("/manicures/{$m->id}/edit")->assertRedirect('/login');
        $this->put("/manicures/{$m->id}", [])->assertRedirect('/login');
        $this->delete("/manicures/{$m->id}")->assertRedirect('/login');
    }

    public function test_date_caption_and_visibility_can_be_changed(): void
    {
        $m = $this->make();

        $this->actingAs($this->me)->put("/manicures/{$m->id}", [
            'worn_on' => '2026-10-16', 'caption' => 'New caption', 'visibility' => 'private',
        ])->assertRedirect("/manicures/{$m->id}");

        $fresh = $m->fresh();
        $this->assertSame('2026-10-16', $fresh->worn_on->toDateString());
        $this->assertSame('New caption', $fresh->caption);
        $this->assertSame('private', $fresh->visibility);
    }

    public function test_choosing_my_usual_setting_clears_the_override(): void
    {
        $m = $this->make();
        $m->forceFill(['visibility' => 'private'])->save();

        $this->actingAs($this->me)->put("/manicures/{$m->id}", ['worn_on' => '2026-10-15', 'visibility' => '']);

        $this->assertNull($m->fresh()->visibility);
    }

    public function test_making_a_manicure_private_hides_it_from_others_immediately(): void
    {
        $m = $this->make();
        $this->get("/manicures/{$m->id}")->assertOk();

        $this->actingAs($this->me)->put("/manicures/{$m->id}", ['worn_on' => '2026-10-15', 'visibility' => 'private']);
        auth()->logout();

        $this->get("/manicures/{$m->id}")->assertNotFound();
    }

    public function test_editing_without_photos_keeps_the_existing_ones(): void
    {
        $m = $this->make(2);

        $this->actingAs($this->me)->put("/manicures/{$m->id}", ['worn_on' => '2026-10-15', 'caption' => 'Changed']);

        $this->assertSame(2, $m->photos()->count());
    }

    public function test_photos_can_be_added_up_to_four_in_total(): void
    {
        $m = $this->make(3);

        $this->actingAs($this->me)->put("/manicures/{$m->id}", [
            'worn_on' => '2026-10-15', 'photos' => [TestImages::upload('x.jpg', 300, 200)],
        ])->assertSessionHasNoErrors();
        $this->assertSame(4, $m->photos()->count());

        $this->actingAs($this->me)->put("/manicures/{$m->id}", [
            'worn_on' => '2026-10-15', 'photos' => [TestImages::upload('y.jpg', 300, 200)],
        ])->assertSessionHasErrors('photos');
        $this->assertSame(4, $m->photos()->count());
    }

    public function test_adding_too_many_at_once_is_refused_and_adds_none(): void
    {
        $m = $this->make(3);

        $this->actingAs($this->me)->put("/manicures/{$m->id}", [
            'worn_on' => '2026-10-15',
            'photos' => [TestImages::upload('x.jpg', 300, 200), TestImages::upload('y.jpg', 300, 200)],
        ])->assertSessionHasErrors('photos');

        $this->assertSame(3, $m->photos()->count());
    }

    public function test_new_photos_go_after_the_existing_ones(): void
    {
        $m = $this->make(2);

        $this->actingAs($this->me)->put("/manicures/{$m->id}", [
            'worn_on' => '2026-10-15', 'photos' => [TestImages::upload('x.jpg', 300, 200)],
        ]);

        $this->assertSame([0, 1, 2], $m->photos()->pluck('position')->all());
    }

    public function test_a_photo_can_be_removed_and_its_files_are_deleted(): void
    {
        $m = $this->make(2);
        $photo = $m->photos->first();
        [$path, $thumb] = [$photo->path, $photo->thumb_path];

        $this->actingAs($this->me)->delete("/manicure-photos/{$photo->id}")->assertRedirect("/manicures/{$m->id}/edit");

        $this->assertNull(ManicurePhoto::find($photo->id));
        Storage::disk('photos')->assertMissing($path);
        Storage::disk('photos')->assertMissing($thumb);
        $this->assertSame(1, $m->photos()->count());
    }

    public function test_the_last_photo_cannot_be_removed(): void
    {
        $m = $this->make(1);
        $photo = $m->photos->first();

        $this->actingAs($this->me)->delete("/manicure-photos/{$photo->id}")->assertSessionHasErrors('photo');

        $this->assertNotNull(ManicurePhoto::find($photo->id));
        Storage::disk('photos')->assertExists($photo->path);
    }

    public function test_rotating_right_turns_the_picture_clockwise_and_refreshes_the_thumbnail(): void
    {
        $photo = $this->make()->photos->first();
        $this->assertTrue($this->isBlack($this->pixel($photo, 'top-left')));
        $this->assertTrue($this->isRed($this->pixel($photo, 'top-right')));

        $this->actingAs($this->me)->post("/manicure-photos/{$photo->id}/rotate", ['direction' => 'right'])->assertRedirect();

        $this->assertSame([600, 800], $this->imageSize($photo));
        $this->assertSame([300, 400], $this->imageSize($photo, true));
        $this->assertTrue($this->isBlack($this->pixel($photo, 'top-right')), 'top-left corner moves to top-right');
        $this->assertTrue($this->isRed($this->pixel($photo, 'bottom-right')), 'top-right corner moves to bottom-right');
    }

    public function test_rotating_left_turns_the_picture_counter_clockwise(): void
    {
        $photo = $this->make()->photos->first();

        $this->actingAs($this->me)->post("/manicure-photos/{$photo->id}/rotate", ['direction' => 'left'])->assertRedirect();

        $this->assertSame([600, 800], $this->imageSize($photo));
        $this->assertTrue($this->isRed($this->pixel($photo, 'top-left')), 'top-right corner moves to top-left');
        $this->assertTrue($this->isBlack($this->pixel($photo, 'bottom-left')), 'top-left corner moves to bottom-left');
    }

    public function test_four_right_turns_bring_it_back_to_the_original_orientation(): void
    {
        $photo = $this->make()->photos->first();

        foreach (range(1, 4) as $_) {
            $this->actingAs($this->me)->post("/manicure-photos/{$photo->id}/rotate", ['direction' => 'right']);
        }

        $this->assertSame([800, 600], $this->imageSize($photo));
        $this->assertTrue($this->isBlack($this->pixel($photo, 'top-left')));
        $this->assertTrue($this->isRed($this->pixel($photo, 'top-right')));
    }

    public function test_an_unknown_rotation_direction_is_refused(): void
    {
        $photo = $this->make()->photos->first();

        $this->actingAs($this->me)->post("/manicure-photos/{$photo->id}/rotate", ['direction' => 'sideways'])
            ->assertSessionHasErrors('direction');
        $this->actingAs($this->me)->post("/manicure-photos/{$photo->id}/rotate", [])->assertSessionHasErrors('direction');

        $this->assertSame([800, 600], $this->imageSize($photo));
    }

    public function test_deleting_a_manicure_deletes_its_rows_and_every_file(): void
    {
        $m = $this->make(3);
        $files = $m->photos->flatMap(fn ($p) => [$p->path, $p->thumb_path])->all();
        $this->assertCount(6, $files);

        $this->actingAs($this->me)->delete("/manicures/{$m->id}")->assertRedirect('/manicures');

        $this->assertNull(Manicure::find($m->id));
        $this->assertSame(0, ManicurePhoto::count());
        foreach ($files as $file) {
            Storage::disk('photos')->assertMissing($file);
        }
    }

    public function test_deleting_one_manicure_leaves_another_alone(): void
    {
        $keep = $this->make();
        $gone = $this->make();

        $this->actingAs($this->me)->delete("/manicures/{$gone->id}");

        $this->assertNotNull(Manicure::find($keep->id));
        Storage::disk('photos')->assertExists($keep->photos->first()->path);
    }

    public function test_the_edit_page_shows_the_photos_and_controls(): void
    {
        $m = $this->make();
        $photo = $m->photos->first();

        $this->actingAs($this->me)->get("/manicures/{$m->id}/edit")->assertOk()
            ->assertSee("/photos/{$photo->id}/thumb", false)
            ->assertSee("/manicure-photos/{$photo->id}/rotate", false)
            ->assertSee('Rotate left')->assertSee('Rotate right');
    }

    // --- per-User photo cap -------------------------------------------------

    public function test_the_per_user_photo_cap_stops_further_uploads(): void
    {
        config(['nailpolish.max_photos_per_user' => 3]);
        $this->make(2);

        $this->actingAs($this->me)->post('/manicures', [
            'worn_on' => '2026-10-15',
            'photos' => [TestImages::upload('a.jpg', 300, 200), TestImages::upload('b.jpg', 300, 200)],
        ])->assertSessionHasErrors('photos');

        $this->assertSame(1, Manicure::count());
    }

    public function test_the_cap_allows_filling_up_to_exactly_the_limit(): void
    {
        config(['nailpolish.max_photos_per_user' => 3]);
        $this->make(2);

        $this->actingAs($this->me)->post('/manicures', [
            'worn_on' => '2026-10-15', 'photos' => [TestImages::upload('a.jpg', 300, 200)],
        ])->assertSessionHasNoErrors();

        $this->assertSame(3, ManicurePhoto::count());
    }

    public function test_the_cap_is_per_user_and_applies_when_adding_to_an_existing_manicure(): void
    {
        config(['nailpolish.max_photos_per_user' => 2]);
        $other = User::factory()->create();
        $this->make(2, $other); // another user's photos don't count against me
        $m = $this->make(2);

        $this->actingAs($this->me)->put("/manicures/{$m->id}", [
            'worn_on' => '2026-10-15', 'photos' => [TestImages::upload('a.jpg', 300, 200)],
        ])->assertSessionHasErrors('photos');
    }

    public function test_removing_photos_frees_up_room_under_the_cap(): void
    {
        config(['nailpolish.max_photos_per_user' => 2]);
        $m = $this->make(2);
        $this->actingAs($this->me)->delete("/manicure-photos/{$m->photos->first()->id}");

        $this->actingAs($this->me)->put("/manicures/{$m->id}", [
            'worn_on' => '2026-10-15', 'photos' => [TestImages::upload('a.jpg', 300, 200)],
        ])->assertSessionHasNoErrors();
    }

    public function test_the_message_when_adding_too_many_says_how_many_more_fit(): void
    {
        $m = $this->make(3);

        $this->actingAs($this->me)->put("/manicures/{$m->id}", [
            'worn_on' => '2026-10-15',
            'photos' => [TestImages::upload('x.jpg', 300, 200), TestImages::upload('y.jpg', 300, 200)],
        ])->assertSessionHasErrors(['photos' => 'This manicure has 3 photos, so you can add 1 more.']);
    }

    public function test_a_failure_deleting_files_does_not_turn_a_successful_delete_into_an_error(): void
    {
        $m = $this->make();
        // Make every file deletion throw, like a storage outage would.
        Storage::shouldReceive('disk')->andReturn(new class {
            public function delete($f) { throw new \RuntimeException('storage down'); }
        });

        $this->actingAs($this->me)->delete("/manicures/{$m->id}")->assertRedirect('/manicures');

        $this->assertNull(Manicure::find($m->id));
    }
}
