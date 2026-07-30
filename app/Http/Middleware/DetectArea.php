<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class DetectArea
{
    public function handle(Request $request, Closure $next)
    {
        $route = $request->route();

        if ($route) {
            $area = $route->getAction('area') ?: 'web';

            app('mystorage')->setCurrentArea($area);

            $request->attributes->set('area', $area);
        }

        return $next($request);
    }
}
