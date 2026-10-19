<?php

namespace App\Http\Controllers;

use App\Models\Manicure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class HeartController extends Controller
{
    public function store(Request $request, string $id)
    {
        $manicure = Manicure::with('user')->findOrFail($id);
        $user = $request->user();

        // Only a Manicure the public can see can be hearted. A private or hidden one answers like a missing one,
        // and that includes the Admin viewing it for review.
        abort_unless($manicure->isPublic(), 404);

        if ($manicure->user_id === $user->id) {
            throw ValidationException::withMessages(['heart' => 'You can\'t heart your own manicure.']);
        }

        // One heart per user per manicure: the primary key makes a repeat a no-op.
        DB::table('hearts')->insertOrIgnore([
            'user_id' => $user->id,
            'manicure_id' => $manicure->id,
            'created_at' => now(),
        ]);

        return back();
    }

    public function destroy(Request $request, string $id)
    {
        // Allowed even if the Manicure has since gone private: you can always take back your own heart.
        DB::table('hearts')->where('user_id', $request->user()->id)->where('manicure_id', $id)->delete();

        return back();
    }
}
