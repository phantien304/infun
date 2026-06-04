<?php

namespace App\Models\Entities;

class ProductOptionDefinition
{
    public function __construct()
    {
        throw new \LogicException(
            'ProductOptionDefinition đã bị deprecate. '.
            'Dùng ProductOption + filter theo Option::ROLE_VARIANT / ROLE_CUSTOM_FIELD.'
        );
    }
}
