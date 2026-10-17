<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\ColorFamily;
use App\Models\Finish;
use App\Models\Polish;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PrivatePolishController extends Controller
{
    public function index(Request $request)
    {
        return view('private-polishes.index', [
            'polishes' => $request->user()->privatePolishes()->with('brand')->get()
                ->sortBy(fn ($p) => $p->brand->name_key.'|'.$p->name_key),
        ]);
    }

    public function create()
    {
        return $this->form(new Polish);
    }

    public function edit(Request $request, string $id)
    {
        return $this->form($this->mine($request, $id)->load(['brand', 'finishes']));
    }

    public function store(Request $request)
    {
        return $this->save($request, new Polish);
    }

    public function update(Request $request, string $id)
    {
        return $this->save($request, $this->mine($request, $id));
    }

    public function destroy(Request $request, string $id)
    {
        $polish = $this->mine($request, $id);
        $swatch = $polish->swatch_path;

        // The foreign key removes the User's Bottles of it too; the form warns about this.
        $polish->delete();

        if ($swatch) {
            Storage::disk('public')->delete($swatch);
        }

        return redirect('/my-polishes')->with('status', 'Private polish deleted.');
    }

    public function suggest(Request $request, string $id)
    {
        $this->mine($request, $id)->forceFill(['promotion_requested_at' => now()])->save();

        return back()->with('status', 'Suggested for the public Catalog. The Admin will review it.');
    }

    public function withdraw(Request $request, string $id)
    {
        $this->mine($request, $id)->forceFill(['promotion_requested_at' => null])->save();

        return back()->with('status', 'Suggestion withdrawn.');
    }

    /** Only the owner's Private Polishes: anything else is a 404, never a 403. */
    private function mine(Request $request, string $id): Polish
    {
        return $request->user()->privatePolishes()->findOrFail($id);
    }

    private function form(Polish $polish)
    {
        return view('private-polishes.form', [
            'polish' => $polish,
            'colorFamilies' => ColorFamily::orderBy('name_key')->get(),
            'finishes' => Finish::orderBy('name_key')->get(),
            'brands' => Brand::withPublicPolishes()->orderBy('name_key')->pluck('name'),
        ]);
    }

    private function save(Request $request, Polish $polish)
    {
        $user = $request->user();

        $request->merge([
            'brand' => trim((string) $request->input('brand')),
            'name' => trim((string) $request->input('name')),
        ]);

        $data = $request->validate([
            'brand' => ['required', 'string', 'max:100'],
            'name' => [
                'required', 'string', 'max:150',
                function ($attribute, $value, $fail) use ($request, $user, $polish) {
                    $brand = Brand::where('name_key', Str::lower($request->input('brand')))->first();

                    if (! $brand) {
                        return;
                    }

                    $key = Str::lower($value);

                    if (Polish::public()->where('brand_id', $brand->id)->where('name_key', $key)->exists()) {
                        $fail('That shade is already in the Catalog. Find it there and mark it owned instead.');
                    } elseif ($user->privatePolishes()->where('brand_id', $brand->id)->where('name_key', $key)
                        ->when($polish->exists, fn ($q) => $q->where('id', '!=', $polish->id))->exists()) {
                        $fail('You already have a private polish with that name.');
                    }
                },
            ],
            'collection' => ['nullable', 'string', 'max:150'],
            'color_family_id' => ['nullable', 'uuid', 'exists:color_families,id'],
            'finish_ids' => ['nullable', 'array'],
            'finish_ids.*' => ['uuid', 'exists:finishes,id'],
            'swatch' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $oldSwatch = null;
        $wasSuggested = $polish->exists && $polish->promotion_requested_at !== null;

        DB::transaction(function () use ($data, $request, $polish, $user, &$oldSwatch) {
            $brand = Brand::where('name_key', Str::lower($data['brand']))->first()
                ?? Brand::create(['name' => $data['brand']]);

            $polish->fill([
                'brand_id' => $brand->id,
                'name' => $data['name'],
                'collection' => $data['collection'] ?? null,
                'color_family_id' => $data['color_family_id'] ?? null,
            ]);
            $polish->user_id = $user->id; // privacy is set here, never taken from the request
            // An edit voids a pending suggestion, so the Admin never promotes content they didn't review.
            $polish->promotion_requested_at = null;

            if ($request->hasFile('swatch')) {
                $oldSwatch = $polish->swatch_path;
                $polish->swatch_path = $request->file('swatch')->store('swatches', 'public');
            }

            $polish->save();
            $polish->finishes()->sync($data['finish_ids'] ?? []);
        });

        if ($oldSwatch) {
            Storage::disk('public')->delete($oldSwatch);
        }

        $message = $wasSuggested ? 'Saved. Your suggestion was withdrawn because you changed the polish; suggest it again when ready.' : 'Saved.';

        return redirect($polish->fresh('brand')->url())->with('status', $message);
    }
}
