<?php

namespace App\Http\Controllers;

use App\Models\Locations;
use Illuminate\Http\Request;

class LocationsController extends Controller
{
    public function index()
    {
        $locations = Locations::all();
        return view('locations.index', compact('locations'));
    }
}
