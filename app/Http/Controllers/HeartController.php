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

        // A private Manicure answers exactly like one that does not exist.
        abort_unless($manicure->visibleTo($user), 404);

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
