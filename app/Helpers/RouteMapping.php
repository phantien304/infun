<?php

namespace App\Helpers;

/**
 * Class RouteMapping
 */
class RouteMapping
{
    /**
     * @param $controller
     * @param $action
     * @return mixed|string
     */
    public static function getRouteText($controller, $action)
    {
        foreach (getAdminRoleConfig('name') as $controllerAction => $text) {
            $controllerAction = explode('|', $controllerAction);
            $controllerName = isset($controllerAction[0]) ? $controllerAction[0] : '';
            $actionName = isset($controllerAction[1]) ? $controllerAction[1] : 'index';
            if ($controllerName == $controller && $actionName == $action) {
                return $text;
            }
        }

        return '';
    }
}
