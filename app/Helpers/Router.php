<?php

namespace App\Helpers;

use Illuminate\Support\Traits\Macroable;

class Router
{
    protected $router;

    use Macroable {
        __call as macroCall;
    }

    public function __construct(\Illuminate\Routing\Router $router)
    {
        $this->router = $router;
    }

    public function __call($method, $parameters)
    {
        $verbs = ['get', 'post', 'put', 'patch', 'delete', 'options', 'any', 'match', 'resource'];
        if (in_array($method, $verbs, true)) {
            return $this->build($method, $parameters);
        }

        if (static::hasMacro($method)) {
            return $this->macroCall($method, $parameters);
        }

        if ($method === 'middleware') {
            return (new RouteRegistrar($this->router))
                ->attribute($method, is_array($parameters[0]) ? $parameters[0] : $parameters);
        }

        return (new RouteRegistrar($this->router))->attribute($method, data_get($parameters, 0));
    }

    public function build($method, $parameters)
    {
        return call_user_func_array([$this->router, $method], $parameters);
    }
}
