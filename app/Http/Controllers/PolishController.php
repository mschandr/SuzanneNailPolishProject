<?php

namespace App\Http\Controllers;

use App\Models\Polish;
use App\Support\CatalogCache;
use Illuminate\Support\Str;

class PolishController extends Controller
{
    public function show(string $brand, string $id, string $slug)
    {
        $requested = "/{$brand}/{$id}/{$slug}";
        $id = Str::lower($id);

        $polish = CatalogCache::remember(
            "polish:{$id}",
            // A miss throws 404 from inside the callback, so it is never cached.
            fn () => Polish::with(['brand', 'colorFamily', 'finishes'])->findOrFail($id),
        );

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
