<?php

namespace App\Http\Controllers;

use App\Models\Bottle;
use App\Models\Manicure;
use App\Models\ManicurePolish;
use App\Models\Polish;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        $target = $this->activeTarget($request, $manicure);
        $layers = $manicure->layers()->where('target', $target)->with('polish.brand')->get();

        // Per-nail mode: how many layers each nail has, for the nail picker. The whole-hand list can be copied from.
        // reorder(): layers() sorts by position, which Postgres rejects together with GROUP BY.
        $counts = $manicure->layers()->reorder()->selectRaw('target, count(*) as n')->groupBy('target')->pluck('n', 'target');
        $handCount = (int) ($counts[ManicurePolish::HAND] ?? 0);
        $nailCounts = $counts->except(ManicurePolish::HAND);

        // The User's own Bottles of anything on this page, for the "link my bottle" pickers.
        $polishIds = $results->pluck('id')->merge($layers->pluck('polish_id'))->unique();
        $bottles = $user->bottles()->whereIn('polish_id', $polishIds)->with('location')->get()->groupBy('polish_id');

        return view('manicures.layers', compact('manicure', 'layers', 'results', 'truncated', 'bottles', 'q', 'target', 'nailCounts', 'handCount') + [
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
            'target' => ['nullable', 'string', 'max:30'],
        ]);

        $polish = Polish::find($data['polish_id']);

        if (! $polish || ! $polish->usableBy($user)) {
            throw ValidationException::withMessages(['polish_id' => 'That polish is not available.']);
        }

        $bottleId = $this->checkedBottleId($user, $polish, $data['bottle_id'] ?? null);

        $target = null;

        // Positions can have gaps (a cascade delete leaves one), so the next slot is max+1, not count.
        // Two simultaneous adds can still pick the same slot; the unique index rejects one, and it retries.
        for ($attempt = 1; ; $attempt++) {
            try {
                DB::transaction(function () use ($manicure, $polish, $bottleId, $data, &$target) {
                    // Read the mode under the lock: a switch made in another tab a moment ago must be honoured.
                    $locked = Manicure::whereKey($manicure->id)->lockForUpdate()->firstOrFail();
                    $target = $this->requestedTarget($locked, $data['target'] ?? null);

                    if ($manicure->layers()->where('target', $target)->count() >= ManicurePolish::MAX_LAYERS) {
                        throw ValidationException::withMessages(['polish_id' => 'This list can have at most '.ManicurePolish::MAX_LAYERS.' layers.']);
                    }

                    $max = $manicure->layers()->where('target', $target)->max('position');

                    $layer = new ManicurePolish;
                    $layer->manicure_id = $manicure->id;
                    $layer->polish_id = $polish->id;
                    $layer->bottle_id = $bottleId;
                    $layer->target = $target;
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

        return redirect($this->listUrl($manicure->id, $target))->with('status', 'Added.');
    }

    public function update(Request $request, string $id)
    {
        $layer = $this->layer($request, $id);
        $data = $request->validate(['bottle_id' => ['nullable', 'uuid']]);

        $layer->bottle_id = $this->checkedBottleId($request->user(), $layer->polish, $data['bottle_id'] ?? null);
        $layer->save();

        return redirect($this->listUrl($layer->manicure_id, $layer->target));
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
            // Only within the same list: a layer never jumps from one nail to another.
            $neighbour = ManicurePolish::where('manicure_id', $layer->manicure_id)->where('target', $layer->target)
                ->when($data['direction'] === 'up',
                    fn ($q) => $q->where('position', '<', $mine)->orderByDesc('position'),
                    fn ($q) => $q->where('position', '>', $mine)->orderBy('position'))
                ->first();

            if (! $neighbour) {
                return; // already at the end
            }

            $targetPosition = $neighbour->position;

            // (manicure_id, target, position) is unique, so park one row out of the way while swapping.
            $layer->forceFill(['position' => 255])->save();
            $neighbour->forceFill(['position' => $mine])->save();
            $layer->forceFill(['position' => $targetPosition])->save();
        });

        return redirect($this->listUrl($layer->manicure_id, $layer->target));
    }

    public function destroy(Request $request, string $id)
    {
        $layer = $this->layer($request, $id);
        $manicureId = $layer->manicure_id;
        $target = $layer->target;

        DB::transaction(function () use ($layer, $manicureId, $target) {
            Manicure::whereKey($manicureId)->lockForUpdate()->first();

            $layer->delete();

            // Close the gap in this list so its positions stay 0..n-1.
            ManicurePolish::where('manicure_id', $manicureId)->where('target', $target)->orderBy('position')->get()
                ->each(fn ($row, $i) => $row->position === $i ?: $row->forceFill(['position' => $i])->save());
        });

        return redirect($this->listUrl($manicureId, $target));
    }

    public function setMode(Request $request, string $id)
    {
        $manicure = $this->manicure($request, $id);
        $data = $request->validate(['mode' => ['required', 'in:'.Manicure::MODE_HAND.','.Manicure::MODE_NAILS]]);

        // Both lists are kept; only the active one is shown and searched. Nothing is deleted by switching.
        $manicure->forceFill(['polish_mode' => $data['mode']])->save();

        return redirect("/manicures/{$manicure->id}/polishes");
    }

    /** Start every nail from the whole-hand list, then the User only has to change the accent nail(s). */
    public function copyToNails(Request $request, string $id)
    {
        $manicure = $this->manicure($request, $id);

        DB::transaction(function () use ($manicure) {
            $locked = Manicure::whereKey($manicure->id)->lockForUpdate()->firstOrFail();

            if ($locked->polish_mode !== Manicure::MODE_NAILS) {
                throw ValidationException::withMessages(['copy' => 'Switch to per-nail mode first.']);
            }

            $hand = $manicure->layers()->where('target', ManicurePolish::HAND)->get();

            if ($hand->isEmpty()) {
                throw ValidationException::withMessages(['copy' => 'The whole-hand list is empty, so there is nothing to copy.']);
            }

            $manicure->layers()->where('target', '!=', ManicurePolish::HAND)->delete();

            $now = now();
            $rows = [];
            foreach (array_keys(Manicure::NAILS) as $nail) {
                foreach ($hand->values() as $position => $layer) {
                    $rows[] = [
                        'id' => (new ManicurePolish)->newUniqueId(),
                        'manicure_id' => $manicure->id,
                        'polish_id' => $layer->polish_id,
                        'bottle_id' => $layer->bottle_id,
                        'target' => $nail,
                        'position' => $position,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }
            ManicurePolish::insert($rows);
        });

        return redirect("/manicures/{$manicure->id}/polishes?nail=".array_key_first(Manicure::NAILS))
            ->with('status', 'Copied the whole-hand list onto every nail. Now change the accent nail(s).');
    }

    /** Which list the labelling page is showing: the hand list, or in per-nail mode the chosen (or first) nail. */
    private function activeTarget(Request $request, Manicure $manicure): string
    {
        if ($manicure->polish_mode !== Manicure::MODE_NAILS) {
            return ManicurePolish::HAND;
        }

        $nail = $request->query('nail');

        return is_string($nail) && array_key_exists($nail, Manicure::NAILS) ? $nail : array_key_first(Manicure::NAILS);
    }

    /** The list a new layer goes on, which must match the Manicure's mode. */
    private function requestedTarget(Manicure $manicure, ?string $target): string
    {
        $nails = array_keys(Manicure::NAILS);

        if ($manicure->polish_mode === Manicure::MODE_NAILS) {
            if (! in_array($target, $nails, true)) {
                throw ValidationException::withMessages(['target' => 'Pick which nail this polish is for.']);
            }

            return $target;
        }

        if ($target === null || $target === '' || $target === ManicurePolish::HAND) {
            return ManicurePolish::HAND;
        }

        throw ValidationException::withMessages(['target' => in_array($target, $nails, true)
            ? 'Switch to per-nail mode first.'
            : 'That nail is not recognised.']);
    }

    private function listUrl(string $manicureId, string $target): string
    {
        return "/manicures/{$manicureId}/polishes".($target === ManicurePolish::HAND ? '' : "?nail={$target}");
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
