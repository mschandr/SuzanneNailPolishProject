<?php

namespace App\Http\Controllers;

use App\Models\Manicure;
use Illuminate\Http\Request;

class ManicureSettingsController extends Controller
{
    public function edit(Request $request)
    {
        return view('settings.manicures', ['current' => $request->user()->manicure_default_visibility]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'manicure_default_visibility' => ['required', 'in:'.Manicure::PUBLIC.','.Manicure::PRIVATE],
        ]);

        $request->user()->forceFill($data)->save();

        return redirect('/settings/manicures')->with('status', 'Saved. This applies to every manicure that follows your usual setting.');
    }
}
