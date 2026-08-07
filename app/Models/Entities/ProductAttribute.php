<?php

namespace App\Models\Entities;

use App\Models\Base\Base;

class ProductAttribute extends Base
{
    protected $table = 'product_attribute';
    public $primaryKey = ['product_id', 'attribute_id', 'language_code'];
    public $incrementing = false;
    public $timestamps = true;
    protected $fillable = ['product_id', 'attribute_id', 'language_code', 'text'];
}
