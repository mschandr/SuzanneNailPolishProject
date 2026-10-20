<?php

namespace App\Http\Controllers;

use App\Jobs\DeleteStoredFiles;
use App\Models\ManicurePhoto;
use App\Models\Polish;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AccountController extends Controller
{
    public function confirmDelete(Request $request)
    {
        $user = $request->user();
        $photos = $this->photos($user)->count();

        return view('account.delete', [
            'counts' => [
                'bottle' => $user->bottles()->count(),
                'location' => $user->locations()->count(),
                'manicure' => $user->manicures()->count(),
                'private polish' => $user->privatePolishes()->count(),
            ],
            'photos' => $photos,
            'lastAdmin' => $this->isLastAdmin($user),
        ]);
    }

    public function destroy(Request $request)
    {
        $user = $request->user();

        $typed = Str::upper(trim((string) $request->input('confirmation')));
        if ($typed !== 'DELETE') {
            throw ValidationException::withMessages(['confirmation' => 'Type DELETE to confirm.']);
        }

        // The rows that name these files are about to disappear, so collect the paths first.
        $files = array_filter($this->storedFiles($user));
        $id = $user->id;
        $email = Str::lower($user->email);

        // The foreign keys remove everything the user owns. Rows keyed by email or by a plain (unconstrained)
        // user_id column are listed here because the database can't cascade them. Deleting by query, not through
        // the model: signing out afterwards saves the model, which would re-insert a deleted user.
        DB::transaction(function () use ($user, $id, $email) {
            // The last-Admin check shares the transaction with the delete and locks the Admin rows, so two Admins
            // deleting themselves at the same moment can't both pass it and leave nobody in charge.
            if ($user->is_admin) {
                User::where('is_admin', true)->lockForUpdate()->pluck('id');

                if ($this->isLastAdmin($user)) {
                    throw ValidationException::withMessages(['confirmation' => 'You are the last Admin. Make someone else an Admin first.']);
                }
            }

            foreach (['magic_links', 'invites', 'invite_requests'] as $table) {
                DB::table($table)->whereRaw('lower(email) = ?', [$email])->delete();
            }
            DB::table('sessions')->where('user_id', $id)->delete();
            User::whereKey($id)->delete();
        });

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($files !== []) {
            try {
                DeleteStoredFiles::dispatch($files);
            } catch (\Throwable $e) {
                // The account is already gone, so this must not become an error page. Keep the paths in the log so
                // the files can be removed by hand rather than orphaned silently.
                report($e);
                Log::error('Account deleted but file cleanup could not be queued', ['files' => $files]);
            }
        }

        return redirect('/')->with('status', 'Your account and everything in it has been permanently deleted.');
    }

    private function isLastAdmin(User $user): bool
    {
        return $user->is_admin && User::where('is_admin', true)->where('id', '!=', $user->id)->doesntExist();
    }

    /** @return array<string, array<int, string>> disk name => paths */
    private function storedFiles(User $user): array
    {
        $photos = $this->photos($user)->get(['path', 'thumb_path'])
            ->flatMap(fn ($p) => [$p->path, $p->thumb_path])->all();

        // Only swatches of polishes still private to this user. A promoted polish is the Catalog's now, and so is its file.
        $swatches = Polish::where('user_id', $user->id)->whereNotNull('swatch_path')->pluck('swatch_path')->all();

        return [config('nailpolish.photos_disk') => $photos, Polish::SWATCH_DISK => $swatches];
    }

    private function photos(User $user)
    {
        return ManicurePhoto::whereIn('manicure_id', $user->manicures()->select('id'));
    }
}
