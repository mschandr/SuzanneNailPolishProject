<?php

namespace App\Http\Controllers;

use App\Models\Polish;
use App\Support\CatalogCache;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;

class PolishController extends Controller
{
    public function show(string $brand, string $id, string $slug)
    {
        $requested = "/{$brand}/{$id}/{$slug}";
        $id = Str::lower($id);

        try {
            // Only public Polishes are cached. A private one misses (404 inside the callback, so nothing is
            // stored) and is read fresh below: edits and deletions show at once and no id probe fills the cache.
            $polish = CatalogCache::remember(
                "polish:{$id}",
                fn () => Polish::public()->with(['brand', 'colorFamily', 'finishes'])->findOrFail($id),
            );
        } catch (ModelNotFoundException) {
            $polish = Polish::with(['brand', 'colorFamily', 'finishes'])->findOrFail($id);
        }

        // Checked on every request, after the cache: the cached copy is shared, the permission is not.
        // A private Polish answers exactly like one that does not exist.
        abort_unless($polish->visibleTo(auth()->user()), 404);

        // The UUID is the identity; slugs are cosmetic and may be stale.
        if ($polish->url() !== $requested) {
            return redirect($polish->url(), 301);
        }

        // Per-user data is read here, outside the shared cache.
        $user = auth()->user();
        $bottles = $user?->bottles()->where('polish_id', $polish->id)->get();
        $locations = $user?->locations()->orderBy('name_key')->get();

        return view('polish.show', ['polish' => $polish, 'bottles' => $bottles, 'locations' => $locations]);
    }
}
