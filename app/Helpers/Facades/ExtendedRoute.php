<?php

namespace App\Helpers\Facades;

use Illuminate\Support\Facades\Facade;

abstract class ExtendedRoute extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'myrouter';
    }
}
