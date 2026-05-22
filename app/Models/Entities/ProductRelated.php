<?php

namespace App\Models\Entities;

use App\Models\Base\Base;

class ProductRelated extends Base
{
    protected $table = 'product_related';
    public $primaryKey = ['product_id', 'related_id'];
    public $incrementing = false;
    public $timestamps = false;

    public function product()
    {
        return $this->belongsTo(Product::class, 'related_id', 'id');
    }
}
