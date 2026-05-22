<?php

namespace App\Http\Middleware;

use App\Repositories\Cms\SettingRepository;
use App\Services\ConfigDbService;
use Closure;
use App\Helpers\Facades\ExtendedRoute as Route;

class CheckJsRequest
{
    public function handle($request, Closure $next)
    {
        if ($request->get('json') && $request->ajax()) {
            return $next($request);
        }
        $data = app()->make(ConfigDbService::class)->getConfig();
        return response()->view('cms.index', ['data' => $data]);
    }
}
