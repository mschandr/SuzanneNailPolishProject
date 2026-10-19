<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class PersonController extends Controller
{
    public function show(Request $request, string $id)
    {
        $person = User::findOrFail($id);

        // Someone who never chose a display name has nothing to show publicly.
        abort_if(blank($person->display_name), 404);

        $viewerIsOwner = $request->user()?->id === $person->id;

        $manicures = $person->manicures()->with('photos', 'user')
            // Strangers see only what is currently public; the owner sees everything, marked by privacy.
            ->when(! $viewerIsOwner, fn ($q) => $q->publiclyVisible())
            ->orderByDesc('worn_on')->orderByDesc('created_at')
            ->paginate(24);

        return view('people.show', [
            'person' => $person,
            'manicures' => $manicures,
            'viewerIsOwner' => $viewerIsOwner,
        ]);
    }
}
