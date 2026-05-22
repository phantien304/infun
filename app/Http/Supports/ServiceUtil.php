<?php

namespace App\Http\Supports;

trait ServiceUtil
{
    /**  */
    protected $_services = [];

    public function registerService(...$services)
    {
        foreach ($services as $value) {
            $this->_services[get_class($value)] = $value;
        }
        return $this;
    }

    public function fetchService($key)
    {
        if ($key) {
            return array_get($this->_services, $key);
        }
        return null;
    }
}