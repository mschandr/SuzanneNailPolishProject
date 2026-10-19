<?php

namespace App\Http\Controllers;

use App\Models\Manicure;
use Illuminate\Http\Request;

class LikedController extends Controller
{
    public function index(Request $request)
    {
        $manicures = Manicure::query()
            ->join('hearts', function ($join) use ($request) {
                $join->on('hearts.manicure_id', '=', 'manicures.id')->where('hearts.user_id', '=', $request->user()->id);
            })
            // Only what is public right now: one that went private drops off, and returns if made public again.
            ->publiclyVisible()
            ->select('manicures.*')
            ->with(['photos', 'user'])
            ->withCount('hearts')
            ->orderByDesc('hearts.created_at')
            ->orderBy('manicures.id') // stable order when several hearts share a second
            ->paginate(24);

        return view('liked.index', ['manicures' => $manicures]);
    }
}
