<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AdminUserController extends Controller
{
    public function index()
    {
        return view('admin.users', ['admins' => User::where('is_admin', true)->orderBy('email')->get()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email']]);

        $user = User::where('email', Str::lower($data['email']))->first();

        if (! $user) {
            return back()->withErrors(['email' => 'No user with that email address has signed in yet.']);
        }

        $user->forceFill(['is_admin' => true])->save();

        return redirect('/admin/users');
    }

    public function destroy(Request $request, User $user)
    {
        if ($user->is($request->user())) {
            return back()->withErrors(['user' => 'You cannot remove your own Admin role.']);
        }

        $user->forceFill(['is_admin' => false])->save();

        return redirect('/admin/users');
    }
}
