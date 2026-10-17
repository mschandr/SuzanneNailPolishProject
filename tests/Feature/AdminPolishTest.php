<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\ColorFamily;
use App\Models\Finish;
use App\Models\Polish;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminPolishTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->admin = User::factory()->admin()->create();
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'brand' => 'Holo Taco',
            'name' => 'Big Apple Red',
            'collection' => 'Spring 2026',
            'color_family_id' => ColorFamily::firstOrCreate(['name_key' => 'red'], ['name' => 'Red'])->id,
            'finish_ids' => [Finish::firstOrCreate(['name_key' => 'creme'], ['name' => 'Creme'])->id],
            'vendor_url' => 'https://holotaco.example/products/big-apple-red',
        ], $overrides);
    }

    public function test_the_admin_creates_a_polish_and_a_new_brand_in_one_step(): void
    {
        $this->actingAs($this->admin)->post('/admin/polishes', $this->payload())->assertRedirect();

        $polish = Polish::with('brand', 'finishes')->firstOrFail();
        $this->assertSame('Holo Taco', $polish->brand->name);
        $this->assertSame('Big Apple Red', $polish->name);
        $this->assertSame(['Creme'], $polish->finishes->pluck('name')->all());
        $this->assertSame(1, Brand::count());
    }

    public function test_an_existing_brand_is_reused_ignoring_case(): void
    {
        Brand::create(['name' => 'Holo Taco']);

        $this->actingAs($this->admin)->post('/admin/polishes', $this->payload(['brand' => ' holo taco ']));

        $this->assertSame(1, Brand::count());
    }

    public function test_after_saving_the_admin_lands_on_the_polish_page(): void
    {
        $response = $this->actingAs($this->admin)->post('/admin/polishes', $this->payload());

        $response->assertRedirect(Polish::firstOrFail()->url());
    }

    public function test_a_swatch_photo_can_be_uploaded(): void
    {
        $this->actingAs($this->admin)->post('/admin/polishes', $this->payload([
            'swatch' => UploadedFile::fake()->image('red.jpg', 600, 600),
        ]));

        $path = Polish::firstOrFail()->swatch_path;
        $this->assertNotNull($path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_a_non_image_swatch_is_refused(): void
    {
        $this->actingAs($this->admin)->post('/admin/polishes', $this->payload([
            'swatch' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf'),
        ]))->assertSessionHasErrors('swatch');

        $this->assertSame(0, Polish::count());
    }

    public function test_a_duplicate_brand_and_shade_is_refused_ignoring_case(): void
    {
        $this->actingAs($this->admin)->post('/admin/polishes', $this->payload());

        $this->actingAs($this->admin)->post('/admin/polishes', $this->payload(['brand' => 'HOLO TACO', 'name' => 'big apple red']))
            ->assertSessionHasErrors('name');

        $this->assertSame(1, Polish::count());
    }

    public function test_the_same_shade_name_under_another_brand_is_allowed(): void
    {
        $this->actingAs($this->admin)->post('/admin/polishes', $this->payload());
        $this->actingAs($this->admin)->post('/admin/polishes', $this->payload(['brand' => 'ILNP']));

        $this->assertSame(2, Polish::count());
    }

    public function test_exactly_one_color_family_is_required(): void
    {
        $this->actingAs($this->admin)->post('/admin/polishes', $this->payload(['color_family_id' => null]))
            ->assertSessionHasErrors('color_family_id');
    }

    public function test_a_polish_may_have_several_finishes(): void
    {
        $ids = [Finish::create(['name' => 'Glitter'])->id, Finish::create(['name' => 'Jelly'])->id];

        $this->actingAs($this->admin)->post('/admin/polishes', $this->payload(['finish_ids' => $ids]));

        $this->assertSame(2, Polish::firstOrFail()->finishes()->count());
    }

    public function test_editing_updates_the_polish_and_keeps_its_identity(): void
    {
        $this->actingAs($this->admin)->post('/admin/polishes', $this->payload());
        $polish = Polish::firstOrFail();

        $this->actingAs($this->admin)->put("/admin/polishes/{$polish->id}", $this->payload([
            'name' => 'Big Apple Crimson',
            'collection' => 'Summer 2026',
        ]))->assertRedirect();

        $fresh = $polish->fresh();
        $this->assertSame($polish->id, $fresh->id);
        $this->assertSame('Big Apple Crimson', $fresh->name);
        $this->assertSame('Summer 2026', $fresh->collection);
    }

    public function test_saving_a_polish_without_changing_its_name_is_not_a_duplicate(): void
    {
        $this->actingAs($this->admin)->post('/admin/polishes', $this->payload());
        $polish = Polish::firstOrFail();

        $this->actingAs($this->admin)->put("/admin/polishes/{$polish->id}", $this->payload(['collection' => 'Changed']))
            ->assertSessionHasNoErrors();
    }

    public function test_non_admins_cannot_create_or_edit(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/admin/polishes/create')->assertForbidden();
        $this->actingAs($user)->post('/admin/polishes', $this->payload())->assertForbidden();
        $this->assertSame(0, Polish::count());
    }
}
