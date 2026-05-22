<?php

namespace App\Helpers;

use Illuminate\Support\Traits\Macroable;

class Router
{
    protected $_uris = [];

    protected $router;

    use Macroable {
        __call as macroCall;
    }

    use RouteAs;

    public function __construct(\Illuminate\Routing\Router $router)
    {
        $this->router = $router;
    }

    public function getUris()
    {
        return $this->_uris;
    }

    public function setUris($uris)
    {
        $this->_uris = $uris;
    }

    public function hasUri($uri)
    {
        return isset($this->_uris[$uri]);
    }

    public function __call($method, $parameters)
    {
        $allow = ['get', 'post', 'any', 'put', 'patch', 'delete', 'options', 'any', 'match'];
        $groups = $this->router->getGroupStack();
        if (in_array($method, $allow)) {
            $this->buildAs($groups, $parameters);
            return $this->buildUri($groups, $method, $parameters);
        }

        if ($method == 'resource') {
            $this->buildAs($groups, $parameters);
            return $this->build($method, $parameters);
        }

        if (static::hasMacro($method)) {
            $this->buildAs($groups, $parameters);
            return $this->macroCall($method, $parameters);
        }

        if ($method == 'middleware') {
            return (new RouteRegistrar($this->router))->attribute($method, is_array($parameters[0]) ? $parameters[0] : $parameters);
        }
        return (new RouteRegistrar($this->router))->attribute($method, data_get($parameters, 0));
    }
    protected function buildUri($groups, $method, $parameters)
    {
        $r = null;
        foreach ($groups as $group) {
            $exs = (array)data_get($group, 'extension', []);
            if (empty($group) || empty($exs)) {
                $r = $this->build($method, $parameters);
                continue;
            }
            foreach ($exs as $ex) {
                if (!$ex) {
                    continue;
                }
                $params = $parameters;
                if ($method == 'match') {
                    $uri = $params[1] = $params[1] . $ex;
                } else {
                    $uri = $params[0] . $ex;
                    $params[0] = $uri;
                }
                $uriMethod = $uri . $method;
                if ($this->hasUri($uriMethod)) {
                    continue;
                }
                $this->_uris[$uriMethod] = $uriMethod;
                $r = $this->build($method, $params);
            }
        }
        return $r ? $r : $this->build($method, $parameters);
    }
    public function build($method, $parameters)
    {
        return call_user_func_array([$this->router, $method], $parameters);
    }
    public static function has($route)
    {
        $routeCollections = \Illuminate\Support\Facades\Route::getRoutes();
        return $routeCollections->hasNamedRoute($route);
    }
    public static function getRouteText($controller, $action)
    {
        return RouteMapping::getRouteText($controller, $action);
    }
}
