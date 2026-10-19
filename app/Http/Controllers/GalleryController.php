<?php

namespace App\Http\Controllers;

use App\Models\ColorFamily;
use App\Models\Finish;
use App\Models\Manicure;
use App\Support\Like;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class GalleryController extends Controller
{
    public function index(Request $request)
    {
        // Only plain strings count: arrays and junk in the query string are treated as "not set".
        $text = fn (string $key) => is_string($v = $request->query($key)) ? trim($v) : '';
        $uuid = fn (string $key) => Str::isUuid($v = $text($key)) ? $v : '';

        $filters = [
            'brand' => $text('brand'),
            'polish' => $text('polish'),
            'person' => $text('person'),
            'finish_id' => $uuid('finish_id'),
            'color_family_id' => $uuid('color_family_id'),
            'polish_id' => $uuid('polish_id'),
        ];
        $sort = $text('sort') === 'loved' ? 'loved' : 'new';

        $query = Manicure::query()
            ->publiclyVisible()
            ->with(['photos', 'user'])
            ->withCount('hearts');

        // Brand, shade, finish and colour all describe one Polish worn in the Manicure, so they must all
        // match the same layer. Only public Polishes are searchable: a private shade never makes a Manicure findable.
        if ($filters['brand'] !== '' || $filters['polish'] !== '' || $filters['finish_id'] !== '' || $filters['color_family_id'] !== '' || $filters['polish_id'] !== '') {
            $query->whereHas('layers', fn ($layer) => $layer->whereHas('polish', function ($polish) use ($filters) {
                $polish->whereNull('polishes.user_id');

                if ($filters['polish'] !== '') {
                    $polish->whereRaw("polishes.name_key like ? escape '!'", [Like::contains($filters['polish'])]);
                }
                if ($filters['brand'] !== '') {
                    $polish->whereHas('brand', fn ($b) => $b->whereRaw("brands.name_key like ? escape '!'", [Like::contains($filters['brand'])]));
                }
                if ($filters['finish_id'] !== '') {
                    $polish->whereHas('finishes', fn ($f) => $f->where('finishes.id', $filters['finish_id']));
                }
                if ($filters['color_family_id'] !== '') {
                    $polish->where('polishes.color_family_id', $filters['color_family_id']);
                }
                if ($filters['polish_id'] !== '') {
                    $polish->where('polishes.id', $filters['polish_id']);
                }
            }));
        }

        if ($filters['person'] !== '') {
            $query->whereHas('user', fn ($u) => $u->whereRaw("lower(users.display_name) like ? escape '!'", [Like::contains($filters['person'])]));
        }

        if ($sort === 'loved') {
            $query->orderByDesc('hearts_count');
        }
        // The id is a last tiebreaker so identical rows keep one stable order across pages.
        $query->orderByDesc('manicures.worn_on')->orderByDesc('manicures.created_at')->orderBy('manicures.id');

        return view('gallery.index', [
            'manicures' => $query->paginate(24)->withQueryString(),
            'filters' => $filters,
            'sort' => $sort,
            'finishes' => Finish::orderBy('name_key')->get(),
            'colorFamilies' => ColorFamily::orderBy('name_key')->get(),
        ]);
    }
}
