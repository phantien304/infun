<?php

namespace App\Http\Middleware;

use Closure;

class LimitAccess
{
    public function handle($request, Closure $next)
    {
        if (config('app.env') !== 'production' || ! config('limit_access.enabled')) {
            return $next($request);
        }

        if (in_array($request->ip(), config('limit_access.ips', []), true)) {
            return $next($request);
        }

        return response('COME HERE BABY', 503);
    }
}
