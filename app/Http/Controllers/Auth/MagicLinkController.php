<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\MagicLinkMail;
use App\Mail\NoAccountMail;
use App\Support\SelfSignup;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use App\Models\MagicLink;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class MagicLinkController extends Controller
{
    public function create()
    {
        return view('auth.login', ['open' => SelfSignup::isOpen()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email:rfc']]);
        $email = Str::lower($data['email']);

        // A suspended account gets no mail at all, with the same on-screen response as everyone else.
        if (User::where('email', $email)->whereNotNull('suspended_at')->exists()) {
            return redirect('/login/sent');
        }

        // Same response either way, so the form can't be used to discover accounts.
        if (! SelfSignup::mayRequestLink($email)) {
            Mail::to($email)->send(new NoAccountMail(url('/invite-request')));

            return redirect('/login/sent');
        }

        $token = Str::random(48);
        MagicLink::create([
            'email' => $email,
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addMinutes(15),
        ]);

        Mail::to($email)->send(new MagicLinkMail(url("/login/{$token}")));

        return redirect('/login/sent');
    }

    // GET only renders a confirm button. Email scanners and link previews
    // issue GETs, so the token is consumed by the POST below, never here.
    public function show(string $token)
    {
        return view('auth.confirm');
    }

    public function consume(Request $request, string $token)
    {
        // The lock keeps two near-simultaneous sign-ups from both taking the last slot.
        try {
            $user = Cache::lock('self-signup', 10)->block(5, fn () => DB::transaction(function () use ($token) {
            $link = MagicLink::where('token_hash', hash('sha256', $token))
                ->whereNull('used_at')
                ->where('expires_at', '>', now())
                ->lockForUpdate()
                ->first();

            if (! $link) {
                return null;
            }

            $user = User::where('email', $link->email)->first();

            if ($user?->isSuspended()) {
                return 'suspended';
            }

            if (! $user) {
                $isAdmin = SelfSignup::isAdminEmail($link->email);

                if (! $isAdmin && ! SelfSignup::isOpen()) {
                    return 'closed';
                }

                $user = new User(['email' => $link->email, 'email_verified_at' => now()]);
                $user->forceFill(['signup_method' => $isAdmin ? 'admin' : 'self'])->save();
            }

            $link->forceFill(['used_at' => now()])->save();

            // Bootstrap only: a brand-new account, or recovery when no Admin exists.
            // Never re-promotes someone an Admin has revoked. is_admin is not mass-assignable.
            if (! $user->is_admin
                && SelfSignup::isAdminEmail($user->email)
                && ($user->wasRecentlyCreated || ! User::where('is_admin', true)->exists())) {
                $user->forceFill(['is_admin' => true])->save();
            }

            return $user;
            }));
        } catch (LockTimeoutException) {
            return redirect('/login')->withErrors(['email' => 'We are busy right now. Please open the link again in a moment.']);
        }

        if ($user === 'suspended') {
            return redirect('/login')->withErrors(['email' => 'This account has been suspended.']);
        }

        if ($user === 'closed') {
            return redirect('/login')->withErrors(['email' => 'New sign-ups have just closed. You can request an invite at /invite-request.']);
        }

        if (! $user) {
            return redirect('/login')->withErrors(['email' => 'That sign-in link is invalid or has expired. Request a new one.']);
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended('/home');
    }
}
