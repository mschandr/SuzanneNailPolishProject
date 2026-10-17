<?php

namespace App\Http\Controllers;

use App\Models\Bottle;
use App\Models\Brand;
use App\Support\CatalogCache;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    public function index()
    {
        return view('catalog.index', [
            'brands' => CatalogCache::remember('brands', fn () => // Only brands with at least one public Polish: a brand that exists solely for someone's
                // Private Polish must not appear.
                Brand::withPublicPolishes()->orderBy('name_key')->get()),
        ]);
    }

    public function brand(Request $request, string $slug)
    {
        // Normalize first so the cache key and the paginator always agree.
        $page = filter_var($request->query('page', 1), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 1;

        $data = CatalogCache::remember("brand:{$slug}:page:{$page}", function () use ($slug, $page) {
            // Misses and out-of-range pages throw 404 from inside the callback, so they are never cached.
            $brand = Brand::where('slug', $slug)->withPublicPolishes()->firstOrFail();
            $polishes = $brand->polishes()->public()->with('brand')->orderBy('name_key')->paginate(24, ['*'], 'page', $page);

            abort_if($page > max(1, $polishes->lastPage()), 404);

            return ['brand' => $brand, 'polishes' => $polishes];
        });

        // Read per request, outside the shared cache, so one User's badges never reach another.
        $ownedIds = $request->user()
            ? $request->user()->bottles()
                ->where('status', Bottle::OWNED)
                ->whereIn('polish_id', $data['polishes']->pluck('id'))
                ->pluck('polish_id')->flip()
            : collect();

        return view('catalog.brand', $data + ['ownedIds' => $ownedIds]);
    }
}
