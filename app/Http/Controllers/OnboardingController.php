<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class OnboardingController extends Controller
{
    /** Each step: a heading, what to do, and where to go to do it. */
    private const STEPS = [
        1 => [
            'title' => 'Add the polishes you own',
            'body' => 'Browse the Catalog by brand, tick the polishes you have, and add them all at once. Pick where they live while you do.',
            'link' => '/catalog',
            'cta' => 'Browse the Catalog',
        ],
        2 => [
            'title' => 'Set up places to keep them',
            'body' => 'Create storage locations such as "China cabinet" or "Under the bed, tray 1". Only you can see them. You can also make one on the spot when you add a polish.',
            'link' => '/locations',
            'cta' => 'Manage locations',
        ],
        3 => [
            'title' => 'Check whether you already own one',
            'body' => 'Standing in a shop? Search your collection by shade or brand to see if you already have it, and where it is.',
            'link' => '/collection',
            'cta' => 'Search my collection',
        ],
        4 => [
            'title' => 'Add shades the Catalog doesn\'t have',
            'body' => 'Limited editions, discontinued shades and indie finds can be added as private polishes. Only you see them, and you can suggest them for the public Catalog.',
            'link' => '/my-polishes',
            'cta' => 'My private polishes',
        ],
    ];

    public function show(Request $request)
    {
        // Anything that isn't a plain 1-4 (including arrays and junk) falls back to step 1.
        $raw = $request->query('step');
        $step = is_string($raw) && ctype_digit($raw) && isset(self::STEPS[(int) $raw]) ? (int) $raw : 1;

        return view('onboarding.show', [
            'step' => $step,
            'total' => count(self::STEPS),
            'content' => self::STEPS[$step],
        ]);
    }

    public function complete(Request $request)
    {
        $user = $request->user();

        // Keep the first completion time: skipping again later, or finishing a replay, changes nothing.
        if ($user->onboarding_completed_at === null) {
            $user->forceFill(['onboarding_completed_at' => now()])->save();
        }

        return redirect('/home');
    }
}
