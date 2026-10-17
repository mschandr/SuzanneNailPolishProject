<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

class LocationController extends Controller
{
    public function index(Request $request)
    {
        return view('locations.index', [
            'locations' => $request->user()->locations()->withCount('bottles')->orderBy('name_key')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $request->user()->locations()->create($this->validated($request));

        return redirect('/locations');
    }

    public function update(Request $request, string $id)
    {
        $location = $request->user()->locations()->findOrFail($id);
        $location->update($this->validated($request, $id));

        return redirect('/locations');
    }

    public function destroy(Request $request, string $id)
    {
        // Bottles keep existing: their location_id is nulled by the foreign key.
        $request->user()->locations()->findOrFail($id)->delete();

        return redirect('/locations');
    }

    private function validated(Request $request, ?string $ignoreId = null): array
    {
        $request->merge(['name' => trim((string) $request->input('name'))]);

        return $request->validate([
            'name' => [
                'required', 'string', 'max:100',
                function ($attribute, $value, $fail) use ($request, $ignoreId) {
                    $taken = $request->user()->locations()
                        ->where('name_key', Str::lower($value))
                        ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                        ->exists();

                    if ($taken) {
                        $fail('You already have a location with that name.');
                    }
                },
            ],
        ]);
    }
}
