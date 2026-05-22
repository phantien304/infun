<?php

namespace App\Helpers\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Class CustomStorage
 * @package App\Helpers\Facades
 */
class CustomStorage extends Facade
{
    /**
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'mystorage';
    }
}
