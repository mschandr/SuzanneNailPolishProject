<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * A response that sets a cookie can't be stored by a shared cache (nginx's fastcgi_cache, a CDN). Public photos
 * don't need the session or CSRF cookie the framework would add, so strip them there and let nginx cache the image.
 * Registered ahead of the session middleware, so this runs after it has added its cookie.
 */
class NoCookiesOnPublicPhotos
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        if ($request->is('photos/*') && str_contains((string) $response->headers->get('Cache-Control'), 'public')) {
            foreach ($response->headers->getCookies() as $cookie) {
                $response->headers->removeCookie($cookie->getName(), $cookie->getPath(), $cookie->getDomain());
            }
        }

        return $response;
    }
}
