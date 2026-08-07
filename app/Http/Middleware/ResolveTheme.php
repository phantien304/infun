<?php

namespace App\Http\Middleware;

use App\Helpers\ThemeManager;
use Closure;
use Illuminate\Http\Request;

class ResolveTheme
{
    public function handle(Request $request, Closure $next)
    {
        ThemeManager::apply(ThemeManager::resolve($request));

        return $next($request);
    }
}
