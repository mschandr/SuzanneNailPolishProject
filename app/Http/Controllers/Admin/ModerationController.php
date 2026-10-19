<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Manicure;
use App\Models\ModerationLog;
use App\Models\Report;
use App\Models\User;
use App\Support\ManicureEraser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Every action changes state and writes its audit entry in one transaction: no unlogged moderation. */
class ModerationController extends Controller
{
    public function index()
    {
        $open = fn ($q) => $q->where('status', 'open');

        return view('admin.moderation', [
            'queue' => Manicure::query()
                ->whereHas('reports', $open)
                ->withCount(['reports as open_reports_count' => $open])
                ->with(['user', 'photos', 'reports' => fn ($q) => $q->where('status', 'open')->with('reporter')->orderBy('created_at')])
                ->orderByDesc('open_reports_count')->orderBy('manicures.created_at')->get(),
            'hidden' => Manicure::whereNotNull('hidden_at')->with('user')->orderByDesc('hidden_at')->get(),
            'suspended' => User::whereNotNull('suspended_at')->orderByDesc('suspended_at')->get(),
        ]);
    }

    public function log()
    {
        return view('admin.moderation-log', [
            'entries' => ModerationLog::with('admin')->orderByDesc('created_at')->orderByDesc('id')->paginate(50),
        ]);
    }

    public function dismiss(Request $request, string $id)
    {
        $manicure = Manicure::with('user')->findOrFail($id);

        $count = DB::transaction(function () use ($request, $manicure) {
            $count = Report::where('manicure_id', $manicure->id)->where('status', 'open')
                ->update(['status' => 'dismissed', 'handled_at' => now()]);

            if ($count > 0) {
                ModerationLog::record($request->user(), 'dismiss_reports', 'manicure', $manicure->id,
                    "Dismissed {$count} ".Str::plural('report', $count)." on a manicure by {$manicure->user->display_name}");
            }

            return $count;
        });

        abort_if($count === 0, 404);

        return redirect('/admin/moderation')->with('status', 'Reports dismissed.');
    }

    public function hide(Request $request, string $id)
    {
        $manicure = $this->moderatable($id);

        // A double click (or two Admins) must not overwrite the first decision or log it twice.
        if ($manicure->hidden_at !== null) {
            return redirect('/admin/moderation')->with('status', 'Already hidden.');
        }

        $reason = $this->reason($request);

        DB::transaction(function () use ($request, $manicure, $reason) {
            $manicure->forceFill(['hidden_at' => now(), 'hidden_reason' => $reason])->save();
            Report::where('manicure_id', $manicure->id)->where('status', 'open')->update(['status' => 'actioned', 'handled_at' => now()]);
            ModerationLog::record($request->user(), 'hide', 'manicure', $manicure->id, $this->describe($manicure), $reason);
        });

        return redirect('/admin/moderation')->with('status', 'Hidden.');
    }

    public function restore(Request $request, string $id)
    {
        $manicure = Manicure::with('user')->findOrFail($id);
        abort_if($manicure->hidden_at === null, 404);

        DB::transaction(function () use ($request, $manicure) {
            $manicure->forceFill(['hidden_at' => null, 'hidden_reason' => null])->save();
            ModerationLog::record($request->user(), 'restore', 'manicure', $manicure->id, $this->describe($manicure, 'Restored'));
        });

        return redirect('/admin/moderation')->with('status', 'Restored.');
    }

    public function destroy(Request $request, string $id)
    {
        $manicure = $this->moderatable($id);
        $reason = $this->reason($request);
        $summary = $this->describe($manicure, 'Deleted'); // taken before it is gone

        // Rows and audit entry commit together; files are removed only after that, so a failure leaves nothing half-done.
        $files = DB::transaction(function () use ($request, $manicure, $summary, $reason) {
            ModerationLog::record($request->user(), 'delete_manicure', 'manicure', $manicure->id, $summary, $reason);

            return ManicureEraser::eraseRecord($manicure);
        });

        ManicureEraser::deleteFiles($files);

        return redirect('/admin/moderation')->with('status', 'Deleted.');
    }

    public function suspend(Request $request, string $id)
    {
        $user = User::findOrFail($id);

        if ($user->is_admin || $user->is($request->user())) {
            throw ValidationException::withMessages(['suspend' => 'An Admin can\'t be suspended, and you can\'t suspend yourself.']);
        }

        $reason = $this->reason($request);

        DB::transaction(function () use ($request, $user, $reason) {
            $user->forceFill(['suspended_at' => now(), 'suspended_reason' => $reason])->save();
            ModerationLog::record($request->user(), 'suspend', 'user', $user->id, "Suspended {$user->display_name}", $reason);
        });

        return redirect('/admin/moderation')->with('status', 'Suspended.');
    }

    public function unsuspend(Request $request, string $id)
    {
        $user = User::findOrFail($id);
        abort_unless($user->isSuspended(), 404);

        DB::transaction(function () use ($request, $user) {
            $user->forceFill(['suspended_at' => null, 'suspended_reason' => null])->save();
            ModerationLog::record($request->user(), 'unsuspend', 'user', $user->id, "Unsuspended {$user->display_name}");
        });

        return redirect('/admin/moderation')->with('status', 'Unsuspended.');
    }

    /** The Admin acts only on a Manicure with an open report, or one that is already hidden. */
    private function moderatable(string $id): Manicure
    {
        $manicure = Manicure::with('user')->findOrFail($id);
        abort_unless($manicure->isModeratable(), 404);

        return $manicure;
    }

    private function reason(Request $request): ?string
    {
        $reason = $request->validate(['reason' => ['nullable', 'string', 'max:300']])['reason'] ?? null;

        return filled($reason) ? trim($reason) : null;
    }

    private function describe(Manicure $manicure, string $verb = 'Hid'): string
    {
        $caption = $manicure->caption ? ': "'.Str::limit($manicure->caption, 80).'"' : '';

        return "{$verb} a manicure by {$manicure->user->display_name}{$caption}";
    }
}
