<?php

namespace App\Http\Controllers;

use App\Models\Bottle;
use App\Models\Brand;
use App\Models\Polish;
use App\Support\CatalogCache;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    private const SEARCH_LIMIT = 48;

    public function index(Request $request)
    {
        $q = is_string($raw = $request->query('q')) ? trim(mb_substr($raw, 0, 100)) : '';

        if ($q !== '') {
            return $this->search($request, $q);
        }

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

        return view('catalog.brand', $data + [
            'ownedIds' => $ownedIds,
            'locations' => $request->user()?->locations()->orderBy('name_key')->get(),
        ]);
    }

    /** Search every public Polish by shade or brand. Never cached, and never includes anyone's Private Polishes. */
    private function search(Request $request, string $q)
    {
        // "!" escapes LIKE wildcards; a backslash inside SQL breaks PDO's Postgres placeholder parsing.
        $like = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($q)).'%';

        $found = Polish::query()->public()
            ->join('brands', 'brands.id', '=', 'polishes.brand_id')
            ->select('polishes.*')
            ->with('brand')
            ->where(fn ($w) => $w
                ->whereRaw("polishes.name_key like ? escape '!'", [$like])
                ->orWhereRaw("brands.name_key like ? escape '!'", [$like]))
            ->orderBy('brands.name_key')->orderBy('polishes.name_key')
            ->limit(self::SEARCH_LIMIT + 1)
            ->get();

        $ownedIds = $request->user()
            ? $request->user()->bottles()->where('status', Bottle::OWNED)
                ->whereIn('polish_id', $found->pluck('id'))->pluck('polish_id')->flip()
            : collect();

        return view('catalog.search', [
            'q' => $q,
            'results' => $found->take(self::SEARCH_LIMIT),
            'truncated' => $found->count() > self::SEARCH_LIMIT,
            'ownedIds' => $ownedIds,
            'limit' => self::SEARCH_LIMIT,
        ]);
    }
}
