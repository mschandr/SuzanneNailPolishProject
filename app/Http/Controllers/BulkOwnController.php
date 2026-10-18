<?php

namespace App\Http\Controllers;

use App\Models\Bottle;
use App\Models\Polish;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BulkOwnController extends Controller
{
    public function store(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'polish_ids' => ['required', 'array', 'min:1', 'max:100'],
            'polish_ids.*' => ['uuid'],
            'location_id' => ['nullable', 'uuid', Rule::exists('locations', 'id')->where('user_id', $user->id)],
            'new_location' => ['nullable', 'string', 'max:100', 'prohibits:location_id'],
        ]);

        $ids = array_values(array_unique($data['polish_ids']));
        $polishes = Polish::whereIn('id', $ids)->get();

        // All or nothing: one unknown or invisible id rejects the whole request.
        if ($polishes->count() !== count($ids) || $polishes->contains(fn (Polish $p) => ! $p->usableBy($user))) {
            throw ValidationException::withMessages(['polish_ids' => 'One or more of the selected polishes is not available.']);
        }

        $newName = filled($data['new_location'] ?? null) ? $data['new_location'] : null;

        // Locking the User's row serializes their own submits, so a double-click or two tabs can't both
        // pass the already-owned check and create duplicate owned Bottles.
        [$added, $skipped] = DB::transaction(function () use ($polishes, $ids, $data, $user, $newName) {
            $user->newQuery()->whereKey($user->id)->lockForUpdate()->first();

            $alreadyOwned = $user->bottles()->where('status', Bottle::OWNED)->whereIn('polish_id', $ids)->pluck('polish_id');
            $toAdd = $polishes->reject(fn (Polish $p) => $alreadyOwned->contains($p->id));

            if ($toAdd->isNotEmpty()) {
                $locationId = $newName !== null ? $user->locationIdNamed($newName) : ($data['location_id'] ?? null);

                foreach ($toAdd as $polish) {
                    $bottle = new Bottle(['polish_id' => $polish->id, 'location_id' => $locationId]);
                    $bottle->user_id = $user->id;
                    $bottle->save();
                }
            }

            return [$toAdd->count(), $alreadyOwned->count()];
        });

        return back()->with('status', $this->message($added, $skipped));
    }

    private function message(int $added, int $skipped): string
    {
        $skippedText = $skipped === 1 ? '1 was already in your collection' : "{$skipped} were already in your collection";

        if ($added === 0) {
            return "Nothing added. {$skippedText}.";
        }

        $addedText = 'Added '.$added.($added === 1 ? ' bottle' : ' bottles');

        return $skipped === 0 ? "{$addedText}." : "{$addedText}. {$skippedText} and ".($skipped === 1 ? 'was' : 'were').' skipped.';
    }
}
