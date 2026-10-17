<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Polish;
use Illuminate\Support\Facades\DB;

class SuggestionController extends Controller
{
    /** Only Polishes whose owners chose to suggest them: other Private Polishes stay invisible to the Admin. */
    public function index()
    {
        return view('admin.suggestions', [
            'polishes' => Polish::whereNotNull('user_id')->whereNotNull('promotion_requested_at')
                ->with(['brand', 'owner'])->orderBy('promotion_requested_at')->get(),
        ]);
    }

    public function promote(string $id)
    {
        $error = DB::transaction(function () use ($id) {
            $polish = $this->suggested($id)->lockForUpdate()->firstOrFail();

            $duplicate = Polish::public()->where('brand_id', $polish->brand_id)->where('name_key', $polish->name_key)->exists();

            if ($duplicate) {
                return 'The public Catalog already has this shade. Decline the suggestion, and the owner can mark the public one as owned.';
            }

            // Public from here on, with no link to the person who created it.
            $polish->user_id = null;
            $polish->promotion_requested_at = null;
            $polish->save();

            return null;
        });

        return $error
            ? back()->withErrors(['promote' => $error])
            : redirect('/admin/suggestions')->with('status', 'Promoted to the public Catalog.');
    }

    public function decline(string $id)
    {
        $this->suggested($id)->firstOrFail()->forceFill(['promotion_requested_at' => null])->save();

        return redirect('/admin/suggestions')->with('status', 'Suggestion declined. It stays private to its owner.');
    }

    private function suggested(string $id)
    {
        return Polish::whereNotNull('user_id')->whereNotNull('promotion_requested_at')->whereKey($id);
    }
}
