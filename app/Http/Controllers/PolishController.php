<?php

namespace App\Http\Controllers;

use App\Models\PolishCollection;
use App\Models\Polishes;
use App\Models\Brand;
use App\Models\Locations;
use Illuminate\Http\Request;

class PolishController extends Controller
{

    public function index()
    {
        $polishes = Polishes::all();
        return view('polishes.index', compact('polishes'));
    }

    public function create()
    {
        $brands = Brand::all();
        $locations = Locations::all();
        $polish_collections = PolishCollection::all();
        return view('polishes.create', compact('brands', 'locations', 'polish_collections'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|unique:polishes|max:255',
            'brand_id' => 'required|integer|exists:brands,id',
            'location_id' => 'required|integer|exists:locations,id',
            'polish_collection_id' => 'nullable|exists:polish_collections,id',
            'polish_type' => 'required|string|max:255',
        ]);

        Polishes::create([
            'name' => $request->input('name'),
            'brand_id' => $request->input('brand_id'),
            'shade' => $request->input('shade'),
            'location_id' => $request->input('location_id'),
            'polish_collection_id' => $request->input('polish_collection_id'),
            'product_url' => null,
            'is_available' => 0,
            'url_check_datetime' => null,
            'polish_type' => $request->input('polish_type'),
        ]);

        return redirect()->route('polishes.index')->with('success', 'Polish added successfully.');
    }

    public function edit(Polishes $polish)
    {
        $brands = Brand::all();
        $locations = Locations::all();
        $polish_collections = PolishCollection::all();
        return view('polishes.edit', compact('polish', 'brands', 'locations',
            'polish_collections'));
    }

    public function update(Request $request, Polishes $polish)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'shade' => 'required|string|max:255',
            'brand_id' => 'required|exists:brands,id',
            'location_id' => 'required|exists:locations,id',
            'polish_collection_id' => 'nullable|exists:polish_collections,id'
        ]);

        $polish->update([
            'name' => $request->input('name'),
            'brand_id' => $request->input('brand_id'),
            'location_id' => $request->input('location_id'),
            'shade' => $request->input('shade'),
            'polish_collection_id' => ($request->input('polish_collection_id') ?? null)
        ]);

        return redirect()->route('polishes.index')->with('success', 'Polish updated successfully.');
    }

    public function destroy(Request $request, Polishes $polish)
    {
        return redirect( )
            ->route('pollishes.index')
            ->with('error', "Polish '{$polish->name}' has nothing associated. Are you sure you want to delete it?") ;

        $polish->delete();

        return redirect()->route('polishes.index')->with('success', 'Brand deleted successfully.');
    }

}
