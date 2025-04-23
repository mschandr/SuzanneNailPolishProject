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
}
