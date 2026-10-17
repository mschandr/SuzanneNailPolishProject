<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\InviteMail;
use App\Models\Invite;
use App\Models\InviteRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class InviteController extends Controller
{
    public function index()
    {
        return view('admin.invites', ['requests' => InviteRequest::orderBy('created_at')->get()]);
    }

    public function store(Request $request)
    {
        $email = Str::lower($request->validate(['email' => ['required', 'email:rfc']])['email']);

        if (User::where('email', $email)->exists()) {
            return back()->withErrors(['email' => 'That address already has an account.']);
        }

        // A new invite replaces any earlier unused one for the same address.
        Invite::where('email', $email)->whereNull('used_at')->delete();

        // The code is generated here, when the invite is sent, and stored hashed.
        $token = Str::random(48);
        Invite::create([
            'email' => $email,
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addDays(30),
        ]);

        InviteRequest::where('email', $email)->update(['invited_at' => now()]);

        Mail::to($email)->send(new InviteMail(url("/invite/{$token}")));

        return redirect('/admin/invites');
    }
}
