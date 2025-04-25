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

    public function create()
    {
        return view('locations.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|unique:locations|max:255',
        ]);

        Locations::create([
            'name' => $request->input('name'),
        ]);

        return redirect()->route('locations.index')->with('success', 'Location added successfully.');
    }

    public function edit(Locations $location)
    {
        return view('locations.edit', compact('location'));
    }

    public function update(Request $request, Locations $location)
    {
        $request->validate([
            'name' => 'required|string|unique:locations|max:255',
        ]);

        $location->update([
            'name' => $request->input('name'),
        ]);

        return redirect()->route('locations.index')->with('success', 'Location updated successfully.');
    }

    public function destroy(Request $request, Locations $location)
    {
        $location->delete();
        return redirect()
            ->route('locations.index')
            ->with('success', 'Location deleted successfully.');

    }
}
