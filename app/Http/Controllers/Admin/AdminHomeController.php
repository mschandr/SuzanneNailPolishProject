<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InviteRequest;
use App\Models\Polish;
use App\Models\Report;

class AdminHomeController extends Controller
{
    public function __invoke()
    {
        return view('admin.index', [
            'needsReview' => Polish::public()->where('needs_review', true)->count(),
            'openReports' => Report::where('status', 'open')->count(),
            'inviteRequests' => InviteRequest::whereNull('invited_at')->count(),
            'suggestions' => Polish::whereNotNull('user_id')->whereNotNull('promotion_requested_at')->count(),
        ]);
    }
}
