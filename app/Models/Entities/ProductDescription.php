<?php

namespace App\Models\Entities;

use App\Models\Base\Base;

class ProductDescription extends Base
{
    protected $table = 'product_description';
    public $primaryKey = ['product_id', 'language_code'];
    public $incrementing = false;
    public $timestamps = true;
}
