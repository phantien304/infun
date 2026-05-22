<?php

namespace App\Http\Middleware;

use Closure;

class HttpsProtocol
{
    public function handle($request, Closure $next)
    {
        if (!$request->secure() && (config('app.env') === 'production' || config('app.env') === 'stg')) {
            return redirect()->secure($request->getRequestUri());
        }
        return $next($request);
    }
}
