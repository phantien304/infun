<?php

namespace App\Http\Middleware;

use Closure;

class Maintenance
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \Closure $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if (setting('config_maintenance') == 1) {
            return redirect()->route('maintenance');
        }
        return $next($request);
    }
}
