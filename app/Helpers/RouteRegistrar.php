<?php

namespace App\Helpers;


class RouteRegistrar extends \Illuminate\Routing\RouteRegistrar
{
    use RouteAs;
    protected $uris = [];
    protected $allowedAttributes = [
        'as',
        'domain',
        'middleware',
        'name',
        'namespace',
        'prefix',
        'extension',
        'area',
        'can',
        'controller',
        'missing',
        'scopeBindings',
        'where',
        'withoutMiddleware',
        'withoutScopedBindings',
    ];
    /**
     * @return array
     */
    public function getUris()
    {
        return $this->uris;
    }
    /**
     * @param array $uris
     */
    public function setUris($uris)
    {
        $this->uris = $uris;
    }
    public function hasUri($uri)
    {
        return isset($this->uris[$uri]);
    }
}
