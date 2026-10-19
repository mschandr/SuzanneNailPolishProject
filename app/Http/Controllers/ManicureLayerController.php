<?php

namespace App\Http\Controllers;

use App\Models\Bottle;
use App\Models\Manicure;
use App\Models\ManicurePolish;
use App\Models\Polish;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ManicureLayerController extends Controller
{
    private const RESULT_LIMIT = 20;

    public function index(Request $request, string $id)
    {
        $manicure = $this->manicure($request, $id);
        $user = $request->user();

        $q = is_string($raw = $request->query('q')) ? trim($raw) : '';
        $results = collect();
        $truncated = false;

        if ($q !== '') {
            // "!" escapes LIKE wildcards; a backslash inside SQL breaks PDO's Postgres placeholder parsing.
            $like = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($q)).'%';

            $found = Polish::query()
                ->join('brands', 'brands.id', '=', 'polishes.brand_id')
                ->select('polishes.*')
                ->with('brand')
                // Public shades plus this User's own private ones; nobody else's private shades, ever.
                ->where(fn ($w) => $w->whereNull('polishes.user_id')->orWhere('polishes.user_id', $user->id))
                ->where(fn ($w) => $w
                    ->whereRaw("polishes.name_key like ? escape '!'", [$like])
                    ->orWhereRaw("brands.name_key like ? escape '!'", [$like]))
                ->orderBy('brands.name_key')->orderBy('polishes.name_key')
                ->limit(self::RESULT_LIMIT + 1)
                ->get();

            $truncated = $found->count() > self::RESULT_LIMIT;
            $results = $found->take(self::RESULT_LIMIT);
        }

        $layers = $manicure->layers()->with('polish.brand')->get();

        // The User's own Bottles of anything on this page, for the "link my bottle" pickers.
        $polishIds = $results->pluck('id')->merge($layers->pluck('polish_id'))->unique();
        $bottles = $user->bottles()->whereIn('polish_id', $polishIds)->with('location')->get()->groupBy('polish_id');

        return view('manicures.layers', compact('manicure', 'layers', 'results', 'truncated', 'bottles', 'q') + [
            'limit' => self::RESULT_LIMIT,
            'full' => $layers->count() >= ManicurePolish::MAX_LAYERS,
        ]);
    }

    public function store(Request $request, string $id)
    {
        $manicure = $this->manicure($request, $id);
        $user = $request->user();

        $data = $request->validate([
            'polish_id' => ['required', 'uuid'],
            'bottle_id' => ['nullable', 'uuid'],
        ]);

        $polish = Polish::find($data['polish_id']);

        if (! $polish || ! $polish->usableBy($user)) {
            throw ValidationException::withMessages(['polish_id' => 'That polish is not available.']);
        }

        $bottleId = $this->checkedBottleId($user, $polish, $data['bottle_id'] ?? null);

        // Positions can have gaps (a cascade delete leaves one), so the next slot is max+1, not count.
        // Two simultaneous adds can still pick the same slot; the unique index rejects one, and it retries.
        for ($attempt = 1; ; $attempt++) {
            try {
                DB::transaction(function () use ($manicure, $polish, $bottleId) {
                    Manicure::whereKey($manicure->id)->lockForUpdate()->first();

                    if ($manicure->layers()->count() >= ManicurePolish::MAX_LAYERS) {
                        throw ValidationException::withMessages(['polish_id' => 'A manicure can list at most '.ManicurePolish::MAX_LAYERS.' layers.']);
                    }

                    $max = $manicure->layers()->max('position');

                    $layer = new ManicurePolish;
                    $layer->manicure_id = $manicure->id;
                    $layer->polish_id = $polish->id;
                    $layer->bottle_id = $bottleId;
                    $layer->position = $max === null ? 0 : $max + 1;
                    $layer->save();
                });

                break;
            } catch (UniqueConstraintViolationException) {
                if ($attempt >= 3) {
                    throw ValidationException::withMessages(['polish_id' => 'Something else was changing this list at the same time. Please try again.']);
                }
            }
        }

        return redirect("/manicures/{$manicure->id}/polishes")->with('status', 'Added.');
    }

    public function update(Request $request, string $id)
    {
        $layer = $this->layer($request, $id);
        $data = $request->validate(['bottle_id' => ['nullable', 'uuid']]);

        $layer->bottle_id = $this->checkedBottleId($request->user(), $layer->polish, $data['bottle_id'] ?? null);
        $layer->save();

        return redirect("/manicures/{$layer->manicure_id}/polishes");
    }

    public function move(Request $request, string $id)
    {
        $layer = $this->layer($request, $id);
        $data = $request->validate(['direction' => ['required', 'in:up,down']]);

        DB::transaction(function () use ($layer, $data) {
            Manicure::whereKey($layer->manicure_id)->lockForUpdate()->first();

            $layer->refresh();
            $mine = $layer->position;

            // The neighbour is the next layer in order, which may not be position +/- 1 if there is a gap.
            $neighbour = ManicurePolish::where('manicure_id', $layer->manicure_id)
                ->when($data['direction'] === 'up',
                    fn ($q) => $q->where('position', '<', $mine)->orderByDesc('position'),
                    fn ($q) => $q->where('position', '>', $mine)->orderBy('position'))
                ->first();

            if (! $neighbour) {
                return; // already at the end
            }

            $targetPosition = $neighbour->position;

            // (manicure_id, position) is unique, so park one row out of the way while swapping.
            $layer->forceFill(['position' => 255])->save();
            $neighbour->forceFill(['position' => $mine])->save();
            $layer->forceFill(['position' => $targetPosition])->save();
        });

        return redirect("/manicures/{$layer->manicure_id}/polishes");
    }

    public function destroy(Request $request, string $id)
    {
        $layer = $this->layer($request, $id);
        $manicureId = $layer->manicure_id;

        DB::transaction(function () use ($layer, $manicureId) {
            Manicure::whereKey($manicureId)->lockForUpdate()->first();

            $layer->delete();

            // Close the gap so positions stay 0..n-1.
            ManicurePolish::where('manicure_id', $manicureId)->orderBy('position')->get()
                ->each(fn ($row, $i) => $row->position === $i ?: $row->forceFill(['position' => $i])->save());
        });

        return redirect("/manicures/{$manicureId}/polishes");
    }

    /** A Bottle link must be the User's own Bottle of that very Polish (any status: history is fine). */
    private function checkedBottleId($user, Polish $polish, ?string $bottleId): ?string
    {
        if (blank($bottleId)) {
            return null;
        }

        $valid = Bottle::where('id', $bottleId)->where('user_id', $user->id)->where('polish_id', $polish->id)->exists();

        if (! $valid) {
            throw ValidationException::withMessages(['bottle_id' => 'That bottle is not yours, or is a different polish.']);
        }

        return $bottleId;
    }

    private function manicure(Request $request, string $id): Manicure
    {
        $manicure = $request->user()->manicures()->findOrFail($id);
        $manicure->abortIfLockedForEditing();

        return $manicure;
    }

    private function layer(Request $request, string $id): ManicurePolish
    {
        $layer = ManicurePolish::whereHas('manicure', fn ($q) => $q->where('user_id', $request->user()->id))
            ->with(['polish', 'manicure'])->findOrFail($id);
        $layer->manicure->abortIfLockedForEditing();

        return $layer;
    }
}
