<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Polish;

class NeedsReviewController extends Controller
{
    public function index()
    {
        return view('admin.needs-review', [
            'polishes' => Polish::public()->where('needs_review', true)
                ->join('brands', 'brands.id', '=', 'polishes.brand_id')
                ->select('polishes.*')
                ->with(['brand', 'colorFamily'])->withCount('finishes')
                ->orderBy('brands.name_key')->orderBy('polishes.name_key')
                ->paginate(50),
        ]);
    }
}
