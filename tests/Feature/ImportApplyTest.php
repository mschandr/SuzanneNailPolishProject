<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\ColorFamily;
use App\Models\Finish;
use App\Models\Import;
use App\Models\ModerationLog;
use App\Models\Polish;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Tests\Support\BuildsGalleryData;
use Tests\TestCase;

class ImportApplyTest extends TestCase
{
    use BuildsGalleryData, RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create(['display_name' => 'Boss']);
        ColorFamily::create(['name' => 'Red']);
        ColorFamily::create(['name' => 'Blue']);
        Finish::create(['name' => 'Creme']);
        Finish::create(['name' => 'Shimmer']);
    }

    private function csv(array $rows, string $header = 'brand,shade,collection,color_family,finishes,vendor_url,image_url'): string
    {
        return $header."\n".implode("\n", $rows)."\n";
    }

    private function upload(array $rows, string $header = 'brand,shade,collection,color_family,finishes,vendor_url,image_url'): Import
    {
        $this->actingAs($this->admin)->post('/admin/imports', [
            'file' => UploadedFile::fake()->createWithContent('p.csv', $this->csv($rows, $header)),
        ])->assertSessionHasNoErrors();

        return Import::orderByDesc('id')->firstOrFail();
    }

    private function apply(Import $import, ?array $rowIds = null, ?User $as = null)
    {
        $rowIds ??= $import->rows()->where('status', 'new')->pluck('id')->all();

        return $this->actingAs($as ?? $this->admin)->post("/admin/imports/{$import->id}/apply", ['rows' => $rowIds]);
    }

    private function rowId(Import $import, string $shade): string
    {
        return $import->rows()->where('name', $shade)->firstOrFail()->id;
    }

    // --- what gets added ---------------------------------------------------------------------------------

    public function test_the_ticked_rows_become_public_polishes_with_everything_matched(): void
    {
        $import = $this->upload(['Indie Co,Ember,Spring 2026,red,Creme; shimmer,https://shop.example/ember,https://img.example/ember.jpg']);

        $this->apply($import)->assertRedirect("/admin/imports/{$import->id}");

        $polish = Polish::with('brand', 'finishes', 'colorFamily')->firstOrFail();
        $this->assertNull($polish->user_id, 'imported polishes are public Catalog polishes');
        $this->assertSame(['Indie Co', 'Ember', 'Spring 2026', 'Red'], [$polish->brand->name, $polish->name, $polish->collection, $polish->colorFamily->name]);
        $this->assertEqualsCanonicalizing(['Creme', 'Shimmer'], $polish->finishes->pluck('name')->all());
        $this->assertSame('https://shop.example/ember', $polish->vendor_url);
        $this->assertSame('https://img.example/ember.jpg', $polish->swatch_url);
        $this->assertFalse((bool) $polish->needs_review, 'colour and finish both matched');
    }

    public function test_only_the_rows_the_admin_ticked_are_added(): void
    {
        $import = $this->upload(['Indie Co,One', 'Indie Co,Two', 'Indie Co,Three'], 'brand,shade');

        $this->apply($import, [$this->rowId($import, 'Two')]);

        $this->assertSame(['Two'], Polish::pluck('name')->all());
    }

    public function test_a_brand_that_exists_is_reused_ignoring_case_and_a_new_one_is_created(): void
    {
        Brand::create(['name' => 'Indie Co']);
        $import = $this->upload(['INDIE CO,One', 'Brand New,Two'], 'brand,shade');

        $this->apply($import);

        $this->assertEqualsCanonicalizing(['Indie Co', 'Brand New'], Brand::pluck('name')->all());
        $this->assertSame(2, Polish::count());
    }

    public function test_rows_that_are_not_new_are_ignored_even_if_someone_posts_their_ids(): void
    {
        $brand = Brand::create(['name' => 'Indie Co']);
        Polish::create(['brand_id' => $brand->id, 'name' => 'Old']);
        $import = $this->upload(['Indie Co,Old', 'Indie Co,Fresh', 'Indie Co,Fresh', ',Broken'], 'brand,shade');

        $this->apply($import, $import->rows()->pluck('id')->all());

        $this->assertEqualsCanonicalizing(['Old', 'Fresh'], Polish::pluck('name')->all());
    }

    public function test_rows_from_a_different_import_cannot_be_smuggled_in(): void
    {
        $other = $this->upload(['Indie Co,Elsewhere'], 'brand,shade');
        $import = $this->upload(['Indie Co,Mine'], 'brand,shade');

        $this->apply($import, [$other->rows()->firstOrFail()->id, $import->rows()->firstOrFail()->id]);

        $this->assertSame(['Mine'], Polish::pluck('name')->all());
    }

    public function test_unknown_ids_and_junk_in_the_selection_are_harmless(): void
    {
        $import = $this->upload(['Indie Co,Mine'], 'brand,shade');
        $good = $import->rows()->firstOrFail()->id;

        $this->apply($import, [$good, 'not-a-uuid', '0198aaaa-0000-7000-8000-000000000000'])->assertSessionHasNoErrors();

        $this->assertSame(1, Polish::count());
    }

    public function test_choosing_nothing_is_refused_with_a_message(): void
    {
        $import = $this->upload(['Indie Co,Mine'], 'brand,shade');

        $this->apply($import, [])->assertSessionHasErrors(['rows' => 'Tick at least one polish to add.']);

        $this->assertSame(0, Polish::count());
        $this->assertSame('preview', $import->fresh()->status, 'still waiting');
    }

    public function test_a_selection_of_only_unusable_rows_adds_nothing_and_says_so(): void
    {
        $import = $this->upload([',Broken'], 'brand,shade');

        $this->apply($import, $import->rows()->pluck('id')->all())->assertSessionHasErrors('rows');
    }

    // --- review flags ----------------------------------------------------------------------------------

    public function test_a_polish_missing_its_colour_or_finish_is_added_and_flagged_for_review(): void
    {
        $import = $this->upload([
            'B,Complete,,Red,Creme',
            'B,No Colour,,Chartreuse,Creme',
            'B,No Finish,,Red,Sparkly',
            'B,Nothing,,,',
        ], 'brand,shade,collection,color_family,finishes');

        $this->apply($import);

        $flags = Polish::pluck('needs_review', 'name')->map(fn ($v) => (bool) $v)->all();
        $this->assertSame(['Complete' => false, 'No Colour' => true, 'No Finish' => true, 'Nothing' => true], $flags);
        $this->assertNull(Polish::firstWhere('name', 'No Colour')->color_family_id);
        $this->assertSame(['Creme'], Polish::firstWhere('name', 'No Colour')->finishes->pluck('name')->all());
        $this->assertSame(0, Polish::firstWhere('name', 'No Finish')->finishes()->count());
    }

    // --- safe to repeat --------------------------------------------------------------------------------

    public function test_applying_twice_adds_nothing_the_second_time(): void
    {
        $import = $this->upload(['Indie Co,One', 'Indie Co,Two'], 'brand,shade');
        $ids = $import->rows()->pluck('id')->all();

        $this->apply($import, $ids);
        $this->apply($import, $ids)->assertRedirect("/admin/imports/{$import->id}");

        $this->assertSame(2, Polish::count());
        $this->assertSame(1, ModerationLog::where('action', 'import_polishes')->count());
    }

    public function test_re_importing_the_same_file_changes_nothing_and_never_touches_edits(): void
    {
        $import = $this->upload(['Indie Co,Ember,Original collection,Red,Creme'], 'brand,shade,collection,color_family,finishes');
        $this->apply($import);
        $polish = Polish::firstOrFail();
        $polish->update(['collection' => 'Edited by the Admin']);

        $again = $this->upload(['Indie Co,Ember,Original collection,Blue,Shimmer'], 'brand,shade,collection,color_family,finishes');

        $this->assertSame('exists', $again->rows()->firstOrFail()->status);
        $this->assertSame(0, $again->rows()->where('status', 'new')->count());
        $this->apply($again, $again->rows()->pluck('id')->all())->assertSessionHasErrors('rows');
        $this->assertSame('Edited by the Admin', $polish->fresh()->collection);
        $this->assertSame('Red', $polish->fresh('colorFamily')->colorFamily->name);
        $this->assertSame(1, Polish::count());
    }

    public function test_a_second_file_with_extra_rows_adds_only_the_new_ones(): void
    {
        $this->apply($this->upload(['Indie Co,One', 'Indie Co,Two'], 'brand,shade'));

        $second = $this->upload(['Indie Co,One', 'Indie Co,Two', 'Indie Co,Three'], 'brand,shade');
        $this->apply($second);

        $this->assertEqualsCanonicalizing(['One', 'Two', 'Three'], Polish::pluck('name')->all());
    }

    public function test_a_polish_created_by_hand_between_preview_and_apply_is_skipped_not_duplicated(): void
    {
        $import = $this->upload(['Indie Co,Race', 'Indie Co,Fine'], 'brand,shade');
        $brand = Brand::firstOrCreate(['name_key' => 'indie co'], ['name' => 'Indie Co']);
        Polish::create(['brand_id' => $brand->id, 'name' => 'Race']); // the Admin added it by hand meanwhile

        $this->apply($import)->assertSessionHas('status', 'Added 1 polish. 1 was already in the Catalog by the time you confirmed, so it was skipped. 1 needs review (missing a color family or finish).');

        $this->assertSame(2, Polish::count());
    }

    public function test_the_summary_says_what_happened(): void
    {
        $import = $this->upload(['B,One,,Red,Creme', 'B,Two,,Chartreuse,Creme', 'B,Three,,Red,Creme'], 'brand,shade,collection,color_family,finishes');

        $this->apply($import, [$this->rowId($import, 'One'), $this->rowId($import, 'Two')])
            ->assertSessionHas('status', 'Added 2 polishes. 1 needs review (missing a color family or finish).');
    }

    public function test_the_import_is_marked_applied_with_its_count_and_can_still_be_viewed(): void
    {
        $import = $this->upload(['B,One', 'B,Two'], 'brand,shade');

        $this->apply($import);

        $fresh = $import->fresh();
        $this->assertSame('applied', $fresh->status);
        $this->assertSame(2, $fresh->applied_count);
        $this->assertNotNull($fresh->applied_at);
        $this->actingAs($this->admin)->get("/admin/imports/{$import->id}")->assertOk()->assertSee('Applied')->assertDontSee('name="rows[]"', false);
    }

    public function test_it_is_written_to_the_admin_audit_log(): void
    {
        $import = $this->upload(['B,One'], 'brand,shade');

        $this->apply($import);

        $entry = ModerationLog::where('action', 'import_polishes')->firstOrFail();
        $this->assertSame($import->id, $entry->target_id);
        $this->assertSame($this->admin->id, $entry->admin_id);
        $this->assertStringContainsString('p.csv', $entry->summary);
    }

    public function test_if_the_audit_entry_cannot_be_written_nothing_is_added(): void
    {
        $import = $this->upload(['B,One'], 'brand,shade');
        Event::listen('eloquent.creating: '.ModerationLog::class, fn () => throw new \RuntimeException('log unavailable'));

        $this->apply($import)->assertStatus(500);

        $this->assertSame(0, Polish::count());
        $this->assertSame('preview', $import->fresh()->status);
    }

    // --- the effect on the site ------------------------------------------------------------------------

    public function test_imported_polishes_are_visible_in_the_catalog_immediately_despite_the_cache(): void
    {
        $this->get('/catalog')->assertOk()->assertDontSee('Fresh Brand'); // warm the cache
        $import = $this->upload(['Fresh Brand,Fresh Shade,,Red,Creme'], 'brand,shade,collection,color_family,finishes');

        $this->apply($import);
        auth()->logout();

        $this->get('/catalog')->assertSee('Fresh Brand');
        $this->get('/catalog/fresh-brand')->assertSee('Fresh Shade');
    }

    public function test_an_imported_polish_shows_its_linked_picture_and_vendor_link(): void
    {
        $import = $this->upload(['B,Pic,https://shop.example/pic,https://img.example/pic.jpg'], 'brand,shade,vendor_url,image_url');
        $this->apply($import);
        $polish = Polish::with('brand')->firstOrFail();

        $page = $this->get($polish->url())->assertOk();

        $page->assertSee('src="https://img.example/pic.jpg"', false)->assertSee('referrerpolicy="no-referrer"', false)
            ->assertSee('href="https://shop.example/pic"', false);
        $this->get('/catalog/b')->assertSee('https://img.example/pic.jpg', false);
    }

    public function test_an_uploaded_swatch_takes_precedence_over_a_linked_one(): void
    {
        $polish = $this->polish('B', 'Both');
        $polish->forceFill(['swatch_path' => 'swatches/own.jpg', 'swatch_url' => 'https://img.example/theirs.jpg'])->save();

        $this->assertStringContainsString('swatches/own.jpg', $polish->swatchSrc());
    }

    public function test_a_stored_link_that_is_not_https_is_never_rendered(): void
    {
        $polish = $this->polish('B', 'Sneaky');
        $polish->forceFill(['swatch_url' => 'javascript:alert(1)'])->save();

        $this->assertNull($polish->fresh()->swatchSrc());
        $this->get($polish->fresh('brand')->url())->assertOk()->assertDontSee('javascript:', false);
    }

    // --- access ------------------------------------------------------------------------------------------

    public function test_applying_an_unknown_import_is_404(): void
    {
        $this->actingAs($this->admin)->post('/admin/imports/0198aaaa-0000-7000-8000-000000000000/apply', ['rows' => ['x']])->assertNotFound();
    }

    public function test_applying_only_some_rows_leaves_the_rest_to_add_later(): void
    {
        $import = $this->upload(['B,One', 'B,Two', 'B,Three'], 'brand,shade');

        $this->apply($import, [$this->rowId($import, 'One')]);

        $fresh = $import->fresh();
        $this->assertSame('preview', $fresh->status, 'two rows are still waiting');
        $this->assertSame(['added', 'new', 'new'], $fresh->rows()->pluck('status')->all());
        $html = $this->actingAs($this->admin)->get("/admin/imports/{$import->id}")->getContent();
        $this->assertSame(2, preg_match_all('/name="rows\[\]"/', $html), 'boxes only for the two still waiting');

        $this->apply($import, [$this->rowId($import, 'Two'), $this->rowId($import, 'Three')]);

        $final = $import->fresh();
        $this->assertSame('applied', $final->status);
        $this->assertSame(3, $final->applied_count);
        $this->assertEqualsCanonicalizing(['One', 'Two', 'Three'], Polish::pluck('name')->all());
    }

    public function test_a_row_already_added_cannot_be_added_again_by_posting_its_id(): void
    {
        $import = $this->upload(['B,One', 'B,Two'], 'brand,shade');
        $one = $this->rowId($import, 'One');
        $this->apply($import, [$one]);

        $this->apply($import, [$one])->assertSessionHasErrors('rows');

        $this->assertSame(1, Polish::where('name', 'One')->count());
    }

    public function test_a_row_skipped_because_it_appeared_meanwhile_is_not_offered_again(): void
    {
        $import = $this->upload(['B,Race', 'B,Fine'], 'brand,shade');
        $brand = Brand::firstOrCreate(['name_key' => 'b'], ['name' => 'B']);
        Polish::create(['brand_id' => $brand->id, 'name' => 'Race']);

        $this->apply($import);

        $this->assertSame(['skipped', 'added'], $import->fresh()->rows()->pluck('status')->all());
        $this->assertSame('applied', $import->fresh()->status);
    }

    public function test_a_brand_created_by_someone_else_a_moment_ago_is_reused_not_a_crash(): void
    {
        $import = $this->upload(['Shiny Brand,One'], 'brand,shade');
        // Another request creates the same brand between the preview and this apply.
        Brand::create(['name' => 'Shiny Brand']);

        $this->apply($import)->assertSessionHasNoErrors();

        $this->assertSame(1, Brand::where('name_key', 'shiny brand')->count());
        $this->assertSame(1, Polish::count());
    }

    public function test_uploaded_swatch_links_are_relative_so_they_work_whatever_address_the_site_is_opened_on(): void
    {
        // APP_URL is often http://localhost:8000 on a dev machine; an absolute link to it is a broken image elsewhere.
        config(['app.url' => 'http://localhost:8000']);
        $polish = $this->polish('B', 'Pretty');
        $polish->forceFill(['swatch_path' => 'swatches/pretty.svg'])->save();

        $this->assertSame('/storage/swatches/pretty.svg', $polish->fresh()->swatchSrc());
        $this->get($polish->fresh('brand')->url())->assertSee('src="/storage/swatches/pretty.svg"', false)->assertDontSee('localhost:8000', false);
    }
}
