<?php

namespace App\Http\Controllers;

use App\Models\Manicure;
use App\Models\Report;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ReportController extends Controller
{
    public function store(Request $request, string $id)
    {
        $manicure = Manicure::with('user')->findOrFail($id);
        $user = $request->user();

        // You can only report what the public can see; anything else answers exactly like a missing Manicure.
        abort_unless($manicure->isPublic(), 404);

        if ($manicure->user_id === $user->id) {
            throw ValidationException::withMessages(['report' => 'You can\'t report your own manicure.']);
        }

        $data = $request->validate([
            'reason' => ['required', 'in:'.implode(',', array_keys(Report::REASONS))],
            'details' => ['nullable', 'string', 'max:1000'],
        ]);

        if (Report::where('manicure_id', $manicure->id)->where('reporter_id', $user->id)->exists()) {
            return back()->with('status', 'You have already reported this manicure.');
        }

        $report = new Report(['reason' => $data['reason'], 'details' => filled($data['details'] ?? null) ? trim($data['details']) : null]);
        $report->manicure_id = $manicure->id;
        $report->reporter_id = $user->id;
        $report->status = 'open';

        try {
            $report->save();
        } catch (UniqueConstraintViolationException) {
            // A double submit: the other request already filed it.
            return back()->with('status', 'You have already reported this manicure.');
        }

        return back()->with('status', 'Thank you. The report has been sent to the Admin.');
    }
}
