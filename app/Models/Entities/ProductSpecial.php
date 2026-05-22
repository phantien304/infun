<?php

namespace App\Models\Entities;

use App\Models\Base\Base;

class ProductSpecial extends Base
{
    protected $table = 'product_special';
    protected $primaryKeyAutoIncrement = 'id';
    public $timestamps = true;
}
