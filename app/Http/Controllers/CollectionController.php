<?php

namespace App\Http\Controllers;

use App\Models\Bottle;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CollectionController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        // Only plain strings are accepted, so ?q[]=x or ?history[]=1 can't crash the page.
        $param = fn (string $key) => is_string($value = $request->query($key)) ? trim($value) : '';
        $rawQ = $param('q');
        $q = Str::lower($rawQ);
        $location = $param('location');
        $history = in_array($param('history'), ['1', 'true', 'on'], true);

        // Always scoped to the signed-in User: nobody else's Bottles can appear.
        $bottles = $user->bottles()
            ->join('polishes', 'polishes.id', '=', 'bottles.polish_id')
            ->join('brands', 'brands.id', '=', 'polishes.brand_id')
            ->select('bottles.*')
            ->with(['polish.brand', 'location'])
            ->when(! $history, fn ($query) => $query->where('bottles.status', Bottle::OWNED))
            ->when($q !== '', function ($query) use ($q) {
                // Escape LIKE wildcards so a search for "%" or "_" is literal. The escape character is "!"
                // because a backslash inside a SQL string breaks PDO's Postgres placeholder parsing.
                $like = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $q).'%';
                $query->where(fn ($w) => $w
                    ->whereRaw("polishes.name_key like ? escape '!'", [$like])
                    ->orWhereRaw("brands.name_key like ? escape '!'", [$like]));
            })
            ->when($location === 'none', fn ($query) => $query->whereNull('bottles.location_id'))
            ->when(Str::isUuid($location), fn ($query) => $query->where('bottles.location_id', $location))
            ->orderBy('brands.name_key')->orderBy('polishes.name_key')->orderBy('bottles.created_at')
            ->paginate(50)
            ->withQueryString();

        return view('collection.index', [
            'bottles' => $bottles,
            'locations' => $user->locations()->orderBy('name_key')->get(),
            'q' => $rawQ,
            'location' => $location,
            'history' => $history,
        ]);
    }
}
