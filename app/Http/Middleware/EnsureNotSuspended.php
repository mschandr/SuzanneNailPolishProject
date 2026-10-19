<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** A suspended User is signed out at their very next request, whatever session they hold. */
class EnsureNotSuspended
{
    public function handle(Request $request, Closure $next)
    {
        if (($user = $request->user()) && $user->isSuspended()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect('/login')->withErrors(['email' => 'This account has been suspended.']);
        }

        return $next($request);
    }
}
