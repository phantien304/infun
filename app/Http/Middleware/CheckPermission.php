<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class CheckPermission
{
    public $keyCache = 'middleware_check_permission';

    public function handle($request, Closure $next)
    {
        //$user = Auth::user();
        //$keyCache = $this->keyCache . $user->email;

        //if ($user->type == getCoreConfig('user.type.member')) {
        //    Auth::logout();
        //    return errNoValidator('Unauthorized', 401);
        //}
        //$action = getDataRoute('action');
        //$controller = preg_split('/(?=[A-Z])/', getDataRoute('controller'));
        //array_walk($controller, function (&$value) {
        //    $value = strtolower($value);
        //});
        //if ($action == getCmsConfig('action_create_or_update')) {
        //    $id = $request->get('id') ?? 0;
        //    $action = ($id > 0) ? 'edit' : 'create';
        //}
        //$permissionsAction = $action . '-' . implode('-', array_filter($controller));

        //if (Cache::has($keyCache)) {
        //    $allPermissionUser = Cache::get($keyCache);
        //} else {
        //    $allPermissionUser = $user->allPermissions()->groupBy('name')->keys()->all();
        //    Cache::add($keyCache, $allPermissionUser);
        //}

        //if (!in_array($permissionsAction, $allPermissionUser)) {
        //    return errNoValidator('Not Permission', 403);
        //}
        return $next($request);
    }
}
