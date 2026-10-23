<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\ColorFamily;
use App\Models\Finish;
use App\Models\Polish;
use App\Support\CatalogCache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PolishController extends Controller
{
    public function create()
    {
        return $this->form(new Polish);
    }

    public function edit(string $id)
    {
        return $this->form(Polish::public()->with(['brand', 'finishes'])->findOrFail($id));
    }

    public function store(Request $request)
    {
        return $this->save($request, new Polish);
    }

    public function update(Request $request, string $id)
    {
        // Private Polishes are never edited here; they become editable once promoted.
        return $this->save($request, Polish::public()->findOrFail($id));
    }

    private function form(Polish $polish)
    {
        return view('admin.polish-form', [
            'polish' => $polish,
            'colorFamilies' => ColorFamily::orderBy('name_key')->get(),
            'finishes' => Finish::orderBy('name_key')->get(),
            'brands' => Brand::withPublicPolishes()->orderBy('name_key')->pluck('name'),
        ]);
    }

    private function save(Request $request, Polish $polish)
    {
        $request->merge([
            'brand' => trim((string) $request->input('brand')),
            'name' => trim((string) $request->input('name')),
        ]);

        $data = $request->validate([
            'brand' => ['required', 'string', 'max:100'],
            'name' => [
                'required', 'string', 'max:150',
                function ($attribute, $value, $fail) use ($request, $polish) {
                    $brand = Brand::where('name_key', Str::lower($request->input('brand')))->first();

                    $taken = $brand && Polish::public()->where('brand_id', $brand->id)
                        ->where('name_key', Str::lower($value))
                        ->when($polish->exists, fn ($q) => $q->where('id', '!=', $polish->id))
                        ->exists();

                    if ($taken) {
                        $fail('That brand already has a polish with this name.');
                    }
                },
            ],
            'collection' => ['nullable', 'string', 'max:150'],
            'color_family_id' => ['required', 'uuid', 'exists:color_families,id'],
            'finish_ids' => ['nullable', 'array'],
            'finish_ids.*' => ['uuid', 'exists:finishes,id'],
            'vendor_url' => ['nullable', 'url:http,https', 'max:2048'],
            'swatch' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $oldSwatch = null;

        DB::transaction(function () use ($data, $request, $polish, &$oldSwatch) {
            $brand = Brand::where('name_key', Str::lower($data['brand']))->first()
                ?? Brand::create(['name' => $data['brand']]);

            $polish->fill([
                'brand_id' => $brand->id,
                'name' => $data['name'],
                'collection' => $data['collection'] ?? null,
                'color_family_id' => $data['color_family_id'],
                'vendor_url' => $data['vendor_url'] ?? null,
            ]);

            if ($request->hasFile('swatch')) {
                $oldSwatch = $polish->swatch_path;
                $polish->swatch_path = $request->file('swatch')->store('swatches', 'public');
            }

            // The form requires a color family but not a finish: a polish an import flagged stays flagged until it has
            // both. (One the Admin makes by hand without a finish is not flagged: that was a choice, not a gap.)
            $polish->needs_review = (bool) $polish->needs_review && empty($data['finish_ids']);

            $polish->save();
            $polish->finishes()->sync($data['finish_ids'] ?? []);
        });

        // Delete the replaced file only once the new row is safely committed.
        if ($oldSwatch) {
            Storage::disk('public')->delete($oldSwatch);
        }

        // sync() fires no model events, so invalidate once everything is saved.
        CatalogCache::bump();

        return redirect($polish->fresh('brand')->url());
    }
}
