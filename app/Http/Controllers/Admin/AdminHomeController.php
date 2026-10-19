<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Polish;

class AdminHomeController extends Controller
{
    public function __invoke()
    {
        return view('admin.index', ['needsReview' => Polish::public()->where('needs_review', true)->count()]);
    }
}
