<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use Illuminate\Http\Request;

class BrandController extends Controller
{
    public function index()
    {
        $brands = Brand::all();
        return view('brands.index', compact('brands'));
    }

    public function create()
    {
        return view('brands.create');
    }

    public function store(Request $request)
    {
        $request->validate([
                               'name' => 'required|string|unique:brands|max:255',
                           ]);

        Brand::create([
                          'name'  => $request->input('name'),
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

