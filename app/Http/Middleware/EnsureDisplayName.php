<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureDisplayName
{
    public function handle(Request $request, Closure $next)
    {
        if (filled($request->user()?->display_name)) {
            return $next($request);
        }

        return redirect('/display-name');
    }
}
