<?php

namespace App\Http\Middleware;

use App\Services\ConfigDbService;
use Closure;

class CheckJsRequest
{
    public function handle($request, Closure $next)
    {
        if ($request->get('json') && $request->ajax()) {
            return $next($request);
        }
        $data = app()->make(ConfigDbService::class)->getConfigs();
        return response()->view('cms::index', ['data' => $data]);
    }
}
