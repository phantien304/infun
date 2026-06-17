<?php

namespace App\Helpers;

class RouteRegistrar extends \Illuminate\Routing\RouteRegistrar
{
    protected $allowedAttributes = [
        'as',
        'domain',
        'middleware',
        'name',
        'namespace',
        'prefix',
        'area',
        'can',
        'controller',
        'missing',
        'scopeBindings',
        'where',
        'withoutMiddleware',
        'withoutScopedBindings',
    ];
}
