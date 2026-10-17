<?php

namespace Tests\Feature;

use App\Models\Bottle;
use App\Models\Brand;
use App\Models\ColorFamily;
use App\Models\Finish;
use App\Models\Polish;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PrivatePolishCrudTest extends TestCase
{
    use RefreshDatabase;

    private User $me;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->me = User::factory()->create();
    }

    private function payload(array $overrides = []): array
    {
        return array_merge(['brand' => 'Indie Co', 'name' => 'Limited Edition Pink'], $overrides);
    }

    private function mine(string $name = 'Limited Edition Pink', string $brand = 'Indie Co', ?User $owner = null): Polish
    {
        $brandModel = Brand::firstOrCreate(['name_key' => strtolower($brand)], ['name' => $brand]);
        $polish = new Polish(['brand_id' => $brandModel->id, 'name' => $name]);
        $polish->user_id = ($owner ?? $this->me)->id;
        $polish->save();

        return $polish;
    }

    public function test_a_guest_is_sent_to_sign_in(): void
    {
        $this->get('/my-polishes')->assertRedirect('/login');
        $this->post('/my-polishes', $this->payload())->assertRedirect('/login');
    }

    public function test_a_user_creates_a_private_polish_owned_by_them(): void
    {
        $this->actingAs($this->me)->post('/my-polishes', $this->payload())->assertRedirect();

        $polish = Polish::firstOrFail();
        $this->assertSame($this->me->id, $polish->user_id);
        $this->assertSame('Limited Edition Pink', $polish->name);
        $this->assertSame('Indie Co', $polish->brand->name);
    }

    public function test_only_brand_and_name_are_required(): void
    {
        $this->actingAs($this->me)->post('/my-polishes', ['brand' => '', 'name' => ''])
            ->assertSessionHasErrors(['brand', 'name']);
        $this->actingAs($this->me)->post('/my-polishes', $this->payload())->assertSessionHasNoErrors();
    }

    public function test_optional_details_can_be_added(): void
    {
        $color = ColorFamily::create(['name' => 'Pink']);
        $finish = Finish::create(['name' => 'Glitter']);

        $this->actingAs($this->me)->post('/my-polishes', $this->payload([
            'collection' => 'Holiday set 2019',
            'color_family_id' => $color->id,
            'finish_ids' => [$finish->id],
            'swatch' => UploadedFile::fake()->image('mine.jpg'),
        ]));

        $polish = Polish::firstOrFail();
        $this->assertSame('Holiday set 2019', $polish->collection);
        $this->assertSame($color->id, $polish->color_family_id);
        $this->assertSame(1, $polish->finishes()->count());
        Storage::disk('public')->assertExists($polish->swatch_path);
    }

    public function test_the_user_cannot_set_the_owner_or_make_it_public_through_the_request(): void
    {
        $other = User::factory()->create();

        $this->actingAs($this->me)->post('/my-polishes', $this->payload(['user_id' => $other->id]));

        $this->assertSame($this->me->id, Polish::firstOrFail()->user_id);
    }

    public function test_a_shade_already_in_the_public_catalog_is_refused_with_a_pointer(): void
    {
        $brand = Brand::create(['name' => 'Indie Co']);
        Polish::create(['brand_id' => $brand->id, 'name' => 'Limited Edition Pink']);

        $this->actingAs($this->me)->post('/my-polishes', $this->payload(['brand' => 'indie co', 'name' => 'LIMITED edition pink']))
            ->assertSessionHasErrors(['name' => 'That shade is already in the Catalog. Find it there and mark it owned instead.']);

        $this->assertSame(1, Polish::count());
    }

    public function test_a_duplicate_of_my_own_private_polish_is_refused(): void
    {
        $this->mine();

        $this->actingAs($this->me)->post('/my-polishes', $this->payload())
            ->assertSessionHasErrors('name');

        $this->assertSame(1, Polish::count());
    }

    public function test_another_users_private_polish_of_the_same_name_does_not_block_me_or_reveal_itself(): void
    {
        $this->mine('Limited Edition Pink', 'Indie Co', User::factory()->create());

        $this->actingAs($this->me)->post('/my-polishes', $this->payload())->assertSessionHasNoErrors();

        $this->assertSame(2, Polish::count());
    }

    public function test_my_list_shows_only_my_private_polishes(): void
    {
        $this->mine('My Secret');
        $this->mine('Their Secret', 'Indie Co', User::factory()->create());

        $this->actingAs($this->me)->get('/my-polishes')->assertOk()->assertSee('My Secret')->assertDontSee('Their Secret');
    }

    public function test_editing_updates_it_and_keeps_it_private(): void
    {
        $polish = $this->mine();

        $this->actingAs($this->me)->put("/my-polishes/{$polish->id}", $this->payload(['name' => 'Renamed', 'collection' => 'Box']))
            ->assertRedirect();

        $fresh = $polish->fresh();
        $this->assertSame('Renamed', $fresh->name);
        $this->assertSame($this->me->id, $fresh->user_id);
    }

    public function test_saving_without_changing_the_name_is_not_a_duplicate(): void
    {
        $polish = $this->mine();

        $this->actingAs($this->me)->put("/my-polishes/{$polish->id}", $this->payload(['collection' => 'Box']))
            ->assertSessionHasNoErrors();
    }

    public function test_someone_elses_private_polish_is_404_to_edit_or_delete(): void
    {
        $theirs = $this->mine('Theirs', 'Indie Co', User::factory()->create());

        $this->actingAs($this->me)->get("/my-polishes/{$theirs->id}/edit")->assertNotFound();
        $this->actingAs($this->me)->put("/my-polishes/{$theirs->id}", $this->payload())->assertNotFound();
        $this->actingAs($this->me)->delete("/my-polishes/{$theirs->id}")->assertNotFound();
        $this->assertNotNull($theirs->fresh());
    }

    public function test_a_public_polish_cannot_be_edited_here(): void
    {
        $brand = Brand::create(['name' => 'Indie Co']);
        $public = Polish::create(['brand_id' => $brand->id, 'name' => 'Public One']);

        $this->actingAs($this->me)->put("/my-polishes/{$public->id}", $this->payload(['name' => 'Hijacked']))->assertNotFound();

        $this->assertSame('Public One', $public->fresh()->name);
    }

    public function test_deleting_removes_it_and_my_bottles_of_it(): void
    {
        $polish = $this->mine();
        $bottle = new Bottle(['polish_id' => $polish->id]);
        $bottle->user_id = $this->me->id;
        $bottle->save();

        $this->actingAs($this->me)->delete("/my-polishes/{$polish->id}")->assertRedirect('/my-polishes');

        $this->assertNull(Polish::find($polish->id));
        $this->assertSame(0, Bottle::count());
    }

    public function test_deleting_removes_its_swatch_file(): void
    {
        $this->actingAs($this->me)->post('/my-polishes', $this->payload(['swatch' => UploadedFile::fake()->image('mine.jpg')]));
        $polish = Polish::firstOrFail();
        $path = $polish->swatch_path;

        $this->actingAs($this->me)->delete("/my-polishes/{$polish->id}");

        Storage::disk('public')->assertMissing($path);
    }
}
