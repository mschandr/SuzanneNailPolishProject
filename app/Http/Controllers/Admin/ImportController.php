<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\ColorFamily;
use App\Models\Finish;
use App\Models\Import;
use App\Models\ImportRow;
use App\Models\ModerationLog;
use App\Models\Polish;
use App\Support\CatalogCache;
use App\Support\PolishCsv;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ImportController extends Controller
{
    public function index()
    {
        return view('admin.imports.index', ['imports' => Import::with('admin')->orderByDesc('created_at')->orderByDesc('id')->limit(20)->get()]);
    }

    public function template()
    {
        return response()->streamDownload(
            fn () => print (PolishCsv::TEMPLATE),
            'polishes-template.csv',
            ['Content-Type' => 'text/csv; charset=UTF-8'],
        );
    }

    public function store(Request $request, PolishCsv $csv)
    {
        $request->validate([
            'file' => ['required', 'file', 'extensions:csv,txt', 'max:'.Import::MAX_KILOBYTES],
        ], [
            'file.extensions' => 'Please upload a .csv file (in your spreadsheet: Save as CSV UTF-8).',
            'file.max' => 'That file is bigger than '.(Import::MAX_KILOBYTES / 1024).' MB.',
        ]);

        $file = $request->file('file');
        $rows = $csv->parse((string) file_get_contents($file->getRealPath()));

        // Nothing is added to the Catalog yet: the rows wait here for the Admin to look at them and choose.
        $import = DB::transaction(function () use ($request, $file, $rows) {
            $import = new Import(['filename' => mb_substr($file->getClientOriginalName(), 0, 200), 'total' => count($rows)]);
            $import->admin_id = $request->user()->id;
            $import->status = 'preview';
            $import->save();

            // The file calls the column "shade"; the table calls it "name". Inserted in bulk, a few hundred at a time.
            $stored = array_map(fn ($r) => ['id' => (new ImportRow)->newUniqueId(), 'import_id' => $import->id, 'name' => $r['shade']]
                + collect($r)->except('shade')->all(), $rows);
            foreach (array_chunk($stored, 250) as $chunk) {
                ImportRow::insert($chunk);
            }

            return $import;
        });

        return redirect("/admin/imports/{$import->id}");
    }

    public function show(string $id)
    {
        $import = Import::with('admin')->findOrFail($id);
        $rows = $import->rows()->get();

        return view('admin.imports.show', [
            'import' => $import,
            'rows' => $rows,
            'counts' => $rows->countBy('status'),
        ]);
    }

    /**
     * Add the ticked rows to the Catalog. Only rows that the preview marked "new" and that belong to this import are
     * considered, whatever the form posted. Existing polishes are never touched: a row whose polish appeared in the
     * meantime is skipped. Safe to repeat: a second apply does nothing.
     */
    public function apply(Request $request, string $id)
    {
        $import = Import::findOrFail($id);

        if ($import->status === 'applied') {
            return redirect("/admin/imports/{$import->id}")->with('status', 'This import was already applied.');
        }

        $ticked = collect((array) $request->input('rows', []))->filter(fn ($v) => is_string($v) && Str::isUuid($v))->unique()->values();
        $rows = $import->rows()->where('status', 'new')->whereIn('id', $ticked)->get();

        if ($rows->isEmpty()) {
            throw ValidationException::withMessages(['rows' => 'Tick at least one polish to add.']);
        }

        $colors = ColorFamily::pluck('id', 'name_key');
        $finishes = Finish::pluck('id', 'name_key');

        // Brands first, each on its own: two requests creating the same brand at once settle on one row. (A unique
        // violation inside the big transaction below would abort it on Postgres, so this is done before it.)
        $brandIds = $rows->pluck('brand')->unique(fn ($b) => Str::lower(trim($b)))->mapWithKeys(fn ($b) => [Str::lower(trim($b)) => $this->brandId($b)]);

        $result = DB::transaction(function () use ($request, $import, $rows, $colors, $finishes, $brandIds) {
            // A double click, or a second Admin, waits here; whoever is second finds it already applied.
            $locked = Import::whereKey($import->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === 'applied') {
                return null;
            }

            $added = $skipped = $review = 0;

            foreach ($rows as $row) {
                $brandId = $brandIds[Str::lower(trim($row->brand))];

                // Someone may have added this polish by hand since the preview: never create a duplicate.
                if (Polish::public()->where('brand_id', $brandId)->where('name_key', Str::lower(trim($row->name)))->exists()) {
                    $row->forceFill(['status' => 'skipped'])->save();
                    $skipped++;

                    continue;
                }

                $colorId = $row->color_family !== null ? ($colors[Str::lower(trim($row->color_family))] ?? null) : null;
                $finishIds = collect(PolishCsv::splitFinishes($row->finishes))->map(fn ($f) => $finishes[Str::lower($f)] ?? null)->filter()->unique()->values();

                $polish = new Polish([
                    'brand_id' => $brandId, 'name' => $row->name, 'collection' => $row->collection,
                    'color_family_id' => $colorId, 'vendor_url' => $row->vendor_url,
                ]);
                // Not mass-assignable: only the importer sets these. A user_id of null makes it a public Catalog polish.
                $incomplete = $colorId === null || $finishIds->isEmpty();
                $polish->forceFill(['swatch_url' => $row->image_url, 'needs_review' => $incomplete])->save();
                $polish->finishes()->sync($finishIds->all());

                $row->forceFill(['status' => 'added'])->save();
                $added++;
                $review += $incomplete ? 1 : 0;
            }

            // Rows the Admin did not tick stay "new": the import is only finished once none are left, so the rest
            // can still be added later instead of needing the file uploaded again.
            $waiting = $locked->rows()->where('status', 'new')->count();
            $locked->forceFill([
                'status' => $waiting === 0 ? 'applied' : 'preview',
                'applied_count' => $locked->applied_count + $added,
                'applied_at' => $waiting === 0 ? now() : null,
            ])->save();
            ModerationLog::record($request->user(), 'import_polishes', 'import', $import->id, "Imported {$added} ".($added === 1 ? 'polish' : 'polishes')." from {$import->filename}");

            return compact('added', 'skipped', 'review');
        });

        if ($result === null) {
            return redirect("/admin/imports/{$import->id}")->with('status', 'This import was already applied.');
        }

        CatalogCache::bump(); // sync() fires no model events, so refresh the public pages once everything is saved

        return redirect("/admin/imports/{$import->id}")->with('status', $this->summary($result));
    }

    /** @param  array{added: int, skipped: int, review: int}  $r */
    private function summary(array $r): string
    {
        $message = "Added {$r['added']} ".($r['added'] === 1 ? 'polish' : 'polishes').'.'; // Laravel's pluralizer leaves "polish" alone

        if ($r['skipped'] > 0) {
            $message .= " {$r['skipped']} ".($r['skipped'] === 1 ? 'was' : 'were').' already in the Catalog by the time you confirmed, so '.($r['skipped'] === 1 ? 'it was' : 'they were').' skipped.';
        }
        if ($r['review'] > 0) {
            $message .= " {$r['review']} ".($r['review'] === 1 ? 'needs' : 'need').' review (missing a colour family or finish).';
        }

        return $message;
    }

    private function brandId(string $name): string
    {
        $key = Str::lower(trim($name));

        if ($id = Brand::where('name_key', $key)->value('id')) {
            return $id;
        }

        try {
            return Brand::create(['name' => $name])->id;
        } catch (UniqueConstraintViolationException) {
            return Brand::where('name_key', $key)->value('id');
        }
    }
}
