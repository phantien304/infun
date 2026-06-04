<?php

namespace App\Models\Entities;

use App\Models\Base\Base;

class ProductVariantDescription extends Base
{
    protected $table = 'product_variant_description';
    protected $primaryKey = ['product_variant_id', 'language_code'];
    public $incrementing = false;
    public $timestamps = true;
}
