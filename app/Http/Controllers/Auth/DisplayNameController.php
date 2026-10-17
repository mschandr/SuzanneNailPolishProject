<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class DisplayNameController extends Controller
{
    public function store(Request $request)
    {
        $request->merge(['display_name' => trim((string) $request->input('display_name'))]);
        $data = $request->validate(['display_name' => ['required', 'string', 'max:40']]);

        $request->user()->update($data);

        return redirect()->intended('/home');
    }
}
