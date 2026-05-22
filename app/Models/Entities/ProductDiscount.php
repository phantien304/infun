<?php

namespace App\Models\Entities;

use App\Models\Base\Base;

class ProductDiscount extends Base
{
    protected $table = 'product_discount';
    protected $primaryKeyAutoIncrement = 'id';
    public $timestamps = true;
}
