<?php

namespace App\Http\Controllers;

use App\Models\Invite;
use App\Models\InviteRequest;
use App\Models\User;
use App\Support\SelfSignup;
use Illuminate\Http\Request;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InviteRequestController extends Controller
{
    public function create()
    {
        return SelfSignup::isOpen() ? redirect('/login') : view('auth.invite-request');
    }

    public function store(Request $request)
    {
        if (SelfSignup::isOpen()) {
            return redirect('/login');
        }

        $email = Str::lower($request->validate(['email' => ['required', 'email:rfc']])['email']);

        // Same response whether or not the address is already known.
        if (! User::where('email', $email)->exists()) {
            try {
                InviteRequest::firstOrCreate(['email' => $email]);
            } catch (UniqueConstraintViolationException) {
                // A concurrent identical request won the race; same outcome.
            }
        }

        return redirect('/invite-request/sent');
    }

    // GET only renders a confirm button, so email scanners cannot use up the code.
    public function showInvite(string $token)
    {
        return view('auth.invite-confirm');
    }

    public function redeem(Request $request, string $token)
    {
        try {
            $user = Cache::lock('self-signup', 10)->block(5, fn () => DB::transaction(function () use ($token) {
            $invite = Invite::where('token_hash', hash('sha256', $token))
                ->whereNull('used_at')
                ->where('expires_at', '>', now())
                ->lockForUpdate()
                ->first();

            if (! $invite) {
                return null;
            }

            $invite->forceFill(['used_at' => now()])->save();

            $user = User::where('email', $invite->email)->first();

            if (! $user) {
                $user = new User(['email' => $invite->email, 'email_verified_at' => now()]);
                $user->forceFill(['signup_method' => 'invite'])->save();
            }

            return $user;
            }));
        } catch (LockTimeoutException) {
            return redirect('/login')->withErrors(['email' => 'We are busy right now. Please open the link again in a moment.']);
        }

        if (! $user) {
            return redirect('/login')->withErrors(['email' => 'That invite is invalid or has expired.']);
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended('/home');
    }
}
