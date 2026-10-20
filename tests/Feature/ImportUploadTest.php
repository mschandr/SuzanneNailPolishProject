<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\ColorFamily;
use App\Models\Finish;
use App\Models\Import;
use App\Models\ImportRow;
use App\Models\Polish;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\Support\BuildsGalleryData;
use Tests\TestCase;

class ImportUploadTest extends TestCase
{
    use BuildsGalleryData, RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create(['display_name' => 'Boss']);
    }

    public static function csv(array $rows, string $header = 'brand,shade,collection,color_family,finishes,vendor_url,image_url'): string
    {
        return $header."\n".implode("\n", $rows)."\n";
    }

    private function upload(string $content, string $name = 'polishes.csv', ?User $as = null)
    {
        $file = UploadedFile::fake()->createWithContent($name, $content);

        return $this->actingAs($as ?? $this->admin)->post('/admin/imports', ['file' => $file]);
    }

    private function rows(Import $import): array
    {
        return $import->rows()->get()->mapWithKeys(fn ($r) => [$r->name => $r->status])->all();
    }

    private function latest(): Import
    {
        return Import::latest('id')->firstOrFail();
    }

    // --- access ---------------------------------------------------------------------------------

    public function test_the_import_page_explains_the_format_and_offers_a_template(): void
    {
        $this->actingAs($this->admin)->get('/admin/imports')->assertOk()
            ->assertSee('template.csv')->assertSee('brand')->assertSee('shade')->assertSee('right to use');
    }

    public function test_the_template_is_a_csv_with_the_right_columns_and_example_rows(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin/imports/template.csv')->assertOk();

        $this->assertStringStartsWith('text/csv', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));
        $lines = explode("\n", trim($response->streamedContent()));
        $this->assertSame('brand,shade,collection,color_family,finishes,vendor_url,image_url', $lines[0]);
        $this->assertGreaterThanOrEqual(2, count($lines));
    }

    public function test_the_template_itself_uploads_cleanly(): void
    {
        $template = $this->actingAs($this->admin)->get('/admin/imports/template.csv')->streamedContent();

        $this->upload($template)->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSame(0, $this->latest()->rows()->where('status', 'invalid')->count());
    }

    // --- the file itself --------------------------------------------------------------------------------

    public function test_a_valid_file_creates_a_preview_and_redirects_to_it(): void
    {
        $this->upload(self::csv(['Indie Co,Ember,Spring,Red,Creme;Shimmer,https://shop.example/ember,https://img.example/ember.jpg']))
            ->assertRedirect();

        $import = $this->latest();
        $this->assertSame('preview', $import->status);
        $this->assertSame('polishes.csv', $import->filename);
        $this->assertSame(1, $import->total);
        $this->assertSame($this->admin->id, $import->admin_id);
        $this->assertSame(0, Polish::count(), 'uploading adds nothing to the Catalog');
        $row = $import->rows()->firstOrFail();
        $this->assertSame(['Indie Co', 'Ember', 'Spring', 'Red', 'Creme;Shimmer', 'new'], [$row->brand, $row->name, $row->collection, $row->color_family, $row->finishes, $row->status]);
    }

    public function test_a_file_is_required(): void
    {
        $this->actingAs($this->admin)->post('/admin/imports', [])->assertSessionHasErrors('file');
    }

    public function test_an_empty_file_is_refused(): void
    {
        $this->upload('')->assertSessionHasErrors('file');
        $this->assertSame(0, Import::count());
    }

    public function test_a_file_with_only_a_header_is_refused(): void
    {
        $this->upload("brand,shade\n")->assertSessionHasErrors('file');
    }

    public function test_a_file_that_is_too_big_is_refused(): void
    {
        $big = UploadedFile::fake()->create('big.csv', 2049, 'text/csv');

        $this->actingAs($this->admin)->post('/admin/imports', ['file' => $big])->assertSessionHasErrors('file');
    }

    public function test_a_file_with_too_many_rows_is_refused_and_nothing_is_kept(): void
    {
        $rows = array_map(fn ($i) => "Brand,Shade {$i}", range(1, Import::MAX_ROWS + 1));

        $this->upload(self::csv($rows, 'brand,shade'))->assertSessionHasErrors('file');

        $this->assertSame(0, Import::count());
        $this->assertSame(0, ImportRow::count());
    }

    public function test_exactly_the_maximum_number_of_rows_is_fine(): void
    {
        $rows = array_map(fn ($i) => "Brand,Shade {$i}", range(1, Import::MAX_ROWS));

        $this->upload(self::csv($rows, 'brand,shade'))->assertSessionHasNoErrors();

        $this->assertSame(Import::MAX_ROWS, $this->latest()->rows()->count());
    }

    public function test_the_required_columns_must_be_present_and_the_error_names_them(): void
    {
        $this->upload(self::csv(['Indie Co,Ember'], 'brand,collection'))
            ->assertSessionHasErrors(['file' => 'The file needs a "shade" column. The first row must be the column names.']);
        $this->upload(self::csv(['Ember'], 'shade'))
            ->assertSessionHasErrors(['file' => 'The file needs a "brand" column. The first row must be the column names.']);
    }

    public function test_column_names_are_case_insensitive_order_free_and_extra_columns_are_ignored(): void
    {
        $this->upload(self::csv(['Ignored,Ember,Indie Co,Red'], 'Notes, SHADE ,Brand,Color_Family'))->assertSessionHasNoErrors();

        $row = $this->latest()->rows()->firstOrFail();
        $this->assertSame(['Indie Co', 'Ember', 'Red'], [$row->brand, $row->name, $row->color_family]);
    }

    public function test_a_byte_order_mark_and_windows_line_endings_are_handled(): void
    {
        $this->upload("\xEF\xBB\xBFbrand,shade\r\nIndie Co,Ember\r\nIndie Co,Plume\r\n")->assertSessionHasNoErrors();

        $this->assertSame(['Ember' => 'new', 'Plume' => 'new'], $this->rows($this->latest()));
    }

    public function test_quoted_fields_with_commas_and_quotes_are_read_correctly(): void
    {
        $this->upload(self::csv(['"Indie, Co","Ember ""Rising""",,,,,'], 'brand,shade,collection,color_family,finishes,vendor_url,image_url'))
            ->assertSessionHasNoErrors();

        $row = $this->latest()->rows()->firstOrFail();
        $this->assertSame(['Indie, Co', 'Ember "Rising"'], [$row->brand, $row->name]);
    }

    public function test_accented_and_non_latin_text_survives(): void
    {
        $this->upload(self::csv(['Café Noir,Crème brûlée 💅,,,,,'], 'brand,shade,collection,color_family,finishes,vendor_url,image_url'))->assertSessionHasNoErrors();

        $row = $this->latest()->rows()->firstOrFail();
        $this->assertSame(['Café Noir', 'Crème brûlée 💅'], [$row->brand, $row->name]);
    }

    public function test_a_file_that_is_not_utf8_gets_a_friendly_message(): void
    {
        $this->upload("brand,shade\nCaf\xE9,Cr\xE8me\n")
            ->assertSessionHasErrors(['file' => 'That file is not UTF-8 text. In your spreadsheet, choose "Save as CSV UTF-8".']);
    }

    public function test_blank_lines_are_skipped_and_line_numbers_match_the_file(): void
    {
        $this->upload("brand,shade\n\nIndie Co,Ember\n,,\nIndie Co,Plume\n")->assertSessionHasNoErrors();

        $this->assertSame([3, 5], $this->latest()->rows()->pluck('line')->all());
    }

    public function test_a_binary_file_is_refused_without_a_server_error(): void
    {
        $this->upload("\x00\x01\x02\xFF\xFE binary \x00")->assertSessionHasErrors('file');
        $this->upload(self::csv(['a,b']), 'picture.png')->assertSessionHasErrors('file');
    }

    // --- row statuses ----------------------------------------------------------------------------------

    public function test_rows_get_a_status_new_exists_duplicate_or_invalid(): void
    {
        $brand = Brand::create(['name' => 'Indie Co']);
        Polish::create(['brand_id' => $brand->id, 'name' => 'Already There']);

        $this->upload(self::csv([
            'Indie Co,Fresh One',
            'INDIE CO,already there',
            'Indie Co,Fresh One',
            ',No Brand',
            'Indie Co,',
        ], 'brand,shade'))->assertSessionHasNoErrors();

        $statuses = $this->latest()->rows()->pluck('status', 'line')->all();
        $this->assertSame([2 => 'new', 3 => 'exists', 4 => 'duplicate', 5 => 'invalid', 6 => 'invalid'], $statuses);
    }

    public function test_a_private_polish_with_the_same_name_does_not_count_as_existing(): void
    {
        $brand = Brand::create(['name' => 'Indie Co']);
        $private = new Polish(['brand_id' => $brand->id, 'name' => 'Same Name']);
        $private->user_id = $this->person('Suzanne')->id;
        $private->save();

        $this->upload(self::csv(['Indie Co,Same Name'], 'brand,shade'));

        $this->assertSame(['Same Name' => 'new'], $this->rows($this->latest()));
    }

    public function test_over_long_values_make_a_row_invalid_with_a_reason(): void
    {
        $this->upload(self::csv([str_repeat('b', 101).',Shade', 'Brand,'.str_repeat('s', 151)], 'brand,shade'));

        $rows = $this->latest()->rows()->get();
        $this->assertSame(['invalid', 'invalid'], $rows->pluck('status')->all());
        $this->assertStringContainsString('Brand name is longer than 100', $rows[0]->problems);
        $this->assertStringContainsString('Shade name is longer than 150', $rows[1]->problems);
    }

    public function test_urls_must_be_http_or_https_and_images_https(): void
    {
        $this->upload(self::csv([
            'B,Good,https://shop.example/x,https://img.example/x.jpg',
            'B,JsLink,javascript:alert(1),',
            'B,FileLink,file:///etc/passwd,',
            'B,HttpImage,https://shop.example/x,http://img.example/x.jpg',
            'B,NoScheme,shop.example/x,',
            'B,Plain Http Shop,http://shop.example/x,',
        ], 'brand,shade,vendor_url,image_url'));

        $this->assertSame(
            ['Good' => 'new', 'JsLink' => 'invalid', 'FileLink' => 'invalid', 'HttpImage' => 'invalid', 'NoScheme' => 'invalid', 'Plain Http Shop' => 'new'],
            $this->rows($this->latest()),
        );
    }

    public function test_an_unknown_colour_or_finish_is_a_warning_not_an_error(): void
    {
        ColorFamily::create(['name' => 'Red']);
        Finish::create(['name' => 'Creme']);

        $this->upload(self::csv(['B,Odd,Chartreuse,Creme;Sparkly'], 'brand,shade,color_family,finishes'));

        $row = $this->latest()->rows()->firstOrFail();
        $this->assertSame('new', $row->status);
        $this->assertStringContainsString('Chartreuse', $row->problems);
        $this->assertStringContainsString('Sparkly', $row->problems);
        $this->assertStringNotContainsString('Creme', $row->problems);
    }

    public function test_known_colours_and_finishes_match_ignoring_case_and_spacing(): void
    {
        ColorFamily::create(['name' => 'Red']);
        Finish::create(['name' => 'Creme']);
        Finish::create(['name' => 'Shimmer']);

        $this->upload(self::csv(['B,Fine,  RED ,creme ; SHIMMER'], 'brand,shade,color_family,finishes'));

        $this->assertNull($this->latest()->rows()->firstOrFail()->problems);
    }

    // --- the preview page ------------------------------------------------------------------------------

    public function test_the_preview_shows_counts_and_a_tickbox_only_for_new_rows_ticked_by_default(): void
    {
        $brand = Brand::create(['name' => 'Indie Co']);
        Polish::create(['brand_id' => $brand->id, 'name' => 'Old One']);
        $this->upload(self::csv(['Indie Co,New One', 'Indie Co,Old One', ',Broken', 'Indie Co,New One'], 'brand,shade'));
        $import = $this->latest();

        $html = $this->actingAs($this->admin)->get("/admin/imports/{$import->id}")->assertOk()->getContent();

        $this->assertStringContainsString('1 new', $html);
        $this->assertStringContainsString('1 already in the Catalog', $html);
        $this->assertStringContainsString('1 duplicate', $html);
        $this->assertStringContainsString('1 problem', $html);
        $this->assertSame(1, preg_match_all('/name="rows\[\]"[^>]*checked/', $html), 'one ticked box, for the one new row');
        $this->assertSame(1, preg_match_all('/name="rows\[\]"/', $html), 'no box at all for the others');
    }

    public function test_the_preview_explains_why_a_row_cannot_be_added(): void
    {
        $this->upload(self::csv([',No Brand'], 'brand,shade'));

        $this->actingAs($this->admin)->get('/admin/imports/'.$this->latest()->id)->assertSee('Brand is required');
    }

    public function test_everything_in_the_file_is_escaped_on_the_preview(): void
    {
        $this->upload(self::csv(['"<script>alert(1)</script>","<img src=x onerror=alert(2)>"'], 'brand,shade'));

        $html = $this->actingAs($this->admin)->get('/admin/imports/'.$this->latest()->id)->getContent();

        $this->assertStringNotContainsString('<script>alert(1)', $html);
        $this->assertStringNotContainsString('<img src=x', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function test_an_unknown_import_is_404_and_non_admins_cannot_view_one(): void
    {
        $this->upload(self::csv(['B,S'], 'brand,shade'));
        $import = $this->latest();

        $this->actingAs($this->admin)->get('/admin/imports/0198aaaa-0000-7000-8000-000000000000')->assertNotFound();
        $this->actingAs($this->person('Plain'))->get("/admin/imports/{$import->id}")->assertForbidden();
    }

    public function test_the_history_lists_recent_imports_newest_first(): void
    {
        $this->upload(self::csv(['B,S1'], 'brand,shade'), 'first.csv');
        $this->travel(5)->minutes();
        $this->upload(self::csv(['B,S2'], 'brand,shade'), 'second.csv');

        $this->actingAs($this->admin)->get('/admin/imports')->assertSeeInOrder(['second.csv', 'first.csv']);
    }

    public function test_unapplied_previews_are_pruned_after_a_week_but_applied_ones_are_kept(): void
    {
        $this->upload(self::csv(['B,Old'], 'brand,shade'));
        $old = $this->latest();
        $this->upload(self::csv(['B,Applied'], 'brand,shade'));
        $applied = $this->latest();
        $applied->forceFill(['status' => 'applied'])->save();
        Import::query()->update(['created_at' => now()->subDays(8)]);
        $this->upload(self::csv(['B,Fresh'], 'brand,shade'));
        $fresh = $this->latest();

        $this->artisan('model:prune', ['--model' => [Import::class]])->assertSuccessful();

        $this->assertNull(Import::find($old->id));
        $this->assertNotNull(Import::find($applied->id));
        $this->assertNotNull(Import::find($fresh->id));
        $this->assertSame(0, ImportRow::where('import_id', $old->id)->count(), 'its rows go with it');
    }

    public function test_line_numbers_are_physical_lines_even_with_a_quoted_newline(): void
    {
        $this->upload("brand,shade,collection\nIndie Co,Ember,\"two\nline note\"\nIndie Co,Plume,\n\nIndie Co,Ember,\n")->assertSessionHasNoErrors();

        $rows = $this->latest()->rows()->get();
        $this->assertSame([2, 4, 6], $rows->pluck('line')->all());
        $this->assertSame('Appears earlier in this file (line 2).', $rows[2]->problems);
    }

    public function test_a_repeated_column_name_is_refused_rather_than_silently_using_the_first(): void
    {
        $this->upload("brand,shade,brand\nA,B,C\n")->assertSessionHasErrors(['file' => 'The column "brand" appears more than once. Each column name may be used once.']);
        $this->assertSame(0, Import::count());
    }

    public function test_two_blank_or_unknown_column_names_are_fine(): void
    {
        $this->upload("brand,shade,,notes,notes\nA,B,x,y,z\n")->assertSessionHasNoErrors();
    }

    public function test_a_big_upload_stores_its_rows_in_few_queries(): void
    {
        $rows = array_map(fn ($i) => "Brand,Shade {$i}", range(1, 1500));
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->upload(self::csv($rows, 'brand,shade'))->assertSessionHasNoErrors();

        $inserts = collect(DB::getQueryLog())->filter(fn ($q) => str_starts_with($q['query'], 'insert into "import_rows"'))->count();
        DB::disableQueryLog();
        $this->assertLessThanOrEqual(10, $inserts, "1500 rows should be a handful of inserts, got {$inserts}");
        $this->assertSame(1500, $this->latest()->rows()->count());
    }
}
