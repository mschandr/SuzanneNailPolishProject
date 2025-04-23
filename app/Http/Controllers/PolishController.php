<?php

namespace App\Http\Controllers;

use App\Models\Polishes;
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
        return view('polishes.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|unique:brands|max:255',
        ]);

        Brand::create([
            'name' => $request->input('name'),
            'total' => 0,
        ]);

        return redirect()->route('brands.index')->with('success', 'Brand added successfully.');
    }

    public function edit(Brand $brand)
    {
        return view('brands.edit', compact('brand'));
    }

    public function update(Request $request, Brand $brand)
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $brand->update([
            'name' => $request->input('name'),
        ]);

        return redirect()->route('brands.index')->with('success', 'Brand updated successfully.');
    }

    public function destroy(Request $request, Brand $brand)
    {
        if ($brand->total > 0 && !$request->has('confirm')) {
            return redirect()
                ->route('brands.index')
                ->with('confirm_delete', $brand->id)
                ->with('error', "Brand '{$brand->name}' has polishes associated. Are you sure you want to delete it?");
        }

        $brand->delete();

        return redirect()->route('brands.index')->with('success', 'Brand deleted successfully.');
    }

}
