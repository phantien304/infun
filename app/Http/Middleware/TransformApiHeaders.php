<?php

namespace App\Http\Middleware;

use Closure;

class TransformApiHeaders
{

    public function handle($request, Closure $next)
    {
        $cookie = $request->cookies;
        $cookieNameXsrf = 'XSRF-TOKEN';
        $tokenCookie = $cookie->get($cookieNameXsrf);

        if ($tokenCookie !== null) {
            $request->headers->add(["X-$cookieNameXsrf" => $tokenCookie]);
        }

        return $next($request);
    }
}
