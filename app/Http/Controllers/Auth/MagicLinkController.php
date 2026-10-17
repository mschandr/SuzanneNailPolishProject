<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\MagicLinkMail;
use App\Models\MagicLink;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class MagicLinkController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email:rfc']]);
        $email = Str::lower($data['email']);

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
        $user = DB::transaction(function () use ($token) {
            $link = MagicLink::where('token_hash', hash('sha256', $token))
                ->whereNull('used_at')
                ->where('expires_at', '>', now())
                ->lockForUpdate()
                ->first();

            if (! $link) {
                return null;
            }

            $link->update(['used_at' => now()]);

            $user = User::firstOrCreate(['email' => $link->email], ['email_verified_at' => now()]);

            // Bootstrap only: a brand-new account, or recovery when no Admin exists.
            // Never re-promotes someone an Admin has revoked. is_admin is not mass-assignable.
            $configured = Str::lower(trim((string) config('nailpolish.admin_email')));
            if ($configured !== ''
                && ! $user->is_admin
                && $user->email === $configured
                && ($user->wasRecentlyCreated || ! User::where('is_admin', true)->exists())) {
                $user->forceFill(['is_admin' => true])->save();
            }

            return $user;
        });

        if (! $user) {
            return redirect('/login')->withErrors(['email' => 'That sign-in link is invalid or has expired. Request a new one.']);
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended('/home');
    }
}
