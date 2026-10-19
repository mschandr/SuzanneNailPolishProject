<?php

namespace App\Http\Controllers;

use App\Models\Manicure;

class CreditsController extends Controller
{
    public function index()
    {
        return view('credits', [
            'manicures' => Manicure::query()->publiclyVisible()->whereNotNull('credit')
                ->with('photos')->orderBy('credit')->get(),
        ]);
    }
}
