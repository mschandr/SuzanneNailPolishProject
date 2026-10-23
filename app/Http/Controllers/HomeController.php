<?php

namespace App\Http\Controllers;

use App\Models\Bottle;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();

        // The tutorial is offered exactly once, the first time a User lands here. Recording that it was
        // offered (not waiting for Skip/Finish) means wandering off never brings it back; Help replays it.
        if ($user->onboarding_completed_at === null) {
            $user->forceFill(['onboarding_completed_at' => now()])->save();

            return redirect('/welcome');
        }

        return view('home', [
            'ownedCount' => $user->bottles()->where('status', Bottle::OWNED)->count(),
            'recent' => $user->bottles()->where('status', Bottle::OWNED)->with('polish.brand')->latest()->limit(4)->get(),
            'manicureCount' => $user->manicures()->count(),
        ]);
    }
}
