<?php

namespace App\Http\Middleware;

use Closure;

class LimitAccess
{
    public function handle($request, Closure $next)
    {
        if (config('app.env') == 'production') {
            $ipArray = ['123.16.25.157', '2001:ee0:4081:bee:9cc0:6f93:7bc7:c4c7'];
            if (in_array(request()->ip(), $ipArray)) {
                return $next($request);
            } else {
                return response("COME HERE BABY", 503);
            }
        } else {
            return $next($request);
        }
    }
}
