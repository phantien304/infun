<?php

namespace App\Models\Entities;

use App\Models\Base\Base;

class ProductImage extends Base
{
    protected $table = 'product_image';
    public $primaryKey = 'id';
    public $incrementing = true;
    public $timestamps = true;
}
