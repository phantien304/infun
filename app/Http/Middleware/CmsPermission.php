<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CmsPermission
{
    protected const MAP = [
        'index'   => 'list',
        'show'    => 'detail',
        'store'   => 'create',
        'update'  => 'edit',
        'destroy' => 'del',
        'restore' => 'del',
        'bulk'    => 'del',
        'reorder'          => 'edit',
        'importCategory'   => 'create',
        'deleteCategories' => 'del',
        'previewTotal'     => 'detail',
        'adminList'        => 'list',
        'permissionsRegistry' => 'list',
        'reply'            => 'edit',
        'resolveReport'    => 'edit',
    ];

    public function handle(Request $request, Closure $next)
    {
        $route      = $request->route();
        $controller = $route?->getController();
        $method     = $route?->getActionMethod();

        if ($controller && \is_object($controller) && \method_exists($controller, 'permissionName')) {
            $slug   = $controller->permissionName();
            $action = self::MAP[$method] ?? null;

            if ($slug !== '' && $action !== null) {
                Gate::authorize($action . '-' . $slug);
            }
        }

        return $next($request);
    }
}
