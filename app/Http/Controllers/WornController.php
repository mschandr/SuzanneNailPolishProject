<?php

namespace App\Http\Controllers;

use App\Models\Worn;
use Illuminate\Http\Request;

class WornController extends Controller
{
    public function index()
    {
        $worn = Worn::all();
        return view('worn.index', compact('worn'));
    }

    public function create()
    {
        return view('worn.create');
    }

    public function store(Request $request)
    {
        $request->validate([]);
    }

    public function recent(Worn $worn, Request $request = null)
    {
        $last = $request->get('last') ?? 5;
        return view('worn.recent', compact('worn', 'last'));
    }

    public function search(Worn $worn)
    {
        return view('worn.search', compact('worn'));
    }
}
