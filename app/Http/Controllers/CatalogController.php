<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Support\CatalogCache;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    public function index()
    {
        return view('catalog.index', [
            'brands' => CatalogCache::remember('brands', fn () => Brand::orderBy('name_key')->get()),
        ]);
    }

    public function brand(Request $request, string $slug)
    {
        // Normalize first so the cache key and the paginator always agree.
        $page = filter_var($request->query('page', 1), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 1;

        $data = CatalogCache::remember("brand:{$slug}:page:{$page}", function () use ($slug, $page) {
            // Misses and out-of-range pages throw 404 from inside the callback, so they are never cached.
            $brand = Brand::where('slug', $slug)->firstOrFail();
            $polishes = $brand->polishes()->with('brand')->orderBy('name_key')->paginate(24, ['*'], 'page', $page);

            abort_if($page > max(1, $polishes->lastPage()), 404);

            return ['brand' => $brand, 'polishes' => $polishes];
        });

        return view('catalog.brand', $data);
    }
}
