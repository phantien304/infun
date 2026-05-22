<?php

namespace App\Http\Supports;

use Route;

trait Permissions
{
    public $_excepts = ['auth', 'cms', 'setting', 'file', 'resource', 'report', 'mail'];
    public $_actions = ['list', 'detail', 'create', 'edit', 'del'];

    public function getPermissionsByController()
    {
        $arrController = $this->getListControllers();
        $excepts = $this->_excepts;
        $actions = $this->_actions;
        $permissions = [];
        foreach ($arrController as $controller) {
            $table = toUnderScore(str_replace('Controller', '', $controller));
            if (!in_array($table, $excepts)) {
                foreach ($actions as $action) {
                    $permissions[$table][$action . '-' . $table] = ['id' => 0, 'is_checked' => 0];
                }
            }
        }
        return $permissions;
    }

    public function getListControllers()
    {
        $controllers = [];
        foreach (Route::getRoutes()->getRoutes() as $route) {
            $action = $route->getAction();
            if (strpos(array_get($action, 'controller', ''), 'App\Http\Controllers' . DIRECTORY_SEPARATOR . ucfirst(getCurrentArea())) !== false) {
                $_action = explode('@', $action['controller']);
                $_namespaces_chunks = explode('\\', $_action[0]);
                $controllers[] = end($_namespaces_chunks);
            }
        }
        return array_unique($controllers);
    }
}
