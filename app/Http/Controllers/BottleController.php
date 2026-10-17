<?php

namespace App\Http\Controllers;

use App\Models\Bottle;
use App\Models\Polish;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BottleController extends Controller
{
    public function store(Request $request, string $polish)
    {
        $polish = Polish::findOrFail($polish);
        $user = $request->user();

        $data = $request->validate([
            'location_id' => ['nullable', 'uuid', Rule::exists('locations', 'id')->where('user_id', $user->id)],
            'new_location' => ['nullable', 'string', 'max:100'],
        ]);

        $locationId = $data['location_id'] ?? null;

        if (filled($data['new_location'] ?? null)) {
            $name = trim($data['new_location']);
            // Typing an existing name reuses that Location instead of failing.
            $locationId = $this->locationIdFor($user, $name);
        }

        $bottle = new Bottle(['polish_id' => $polish->id, 'location_id' => $locationId]);
        $bottle->user_id = $user->id;
        $bottle->save();

        return back()->with('status', 'Added to your collection.');
    }

    public function update(Request $request, string $id)
    {
        $bottle = $request->user()->bottles()->findOrFail($id);

        $data = $request->validate([
            'location_id' => ['sometimes', 'nullable', 'uuid', Rule::exists('locations', 'id')->where('user_id', $request->user()->id)],
            'status' => ['sometimes', Rule::in(Bottle::STATUSES)],
        ]);

        // Only fields that were sent change, so setting a status never clears the location.
        $bottle->forceFill($data)->save();

        $message = 'Saved.';
        if (($data['status'] ?? Bottle::OWNED) !== Bottle::OWNED) {
            $message .= ' This bottle is now in your history.';
        }

        return back()->with('status', $message);
    }

    public function destroy(Request $request, string $id)
    {
        $request->user()->bottles()->findOrFail($id)->delete();

        return back()->with('status', 'Bottle removed.');
    }

    /** Reuse the User's Location of that name, or create it; a double-submit race falls back to the winner's row. */
    private function locationIdFor($user, string $name): string
    {
        $find = fn () => $user->locations()->where('name_key', \Illuminate\Support\Str::lower($name))->value('id');

        if ($id = $find()) {
            return $id;
        }

        try {
            return $user->locations()->create(['name' => $name])->id;
        } catch (UniqueConstraintViolationException) {
            return $find();
        }
    }
}
