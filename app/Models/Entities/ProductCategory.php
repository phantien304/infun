<?php

namespace App\Models\Entities;

use App\Models\Base\Base;

class ProductCategory extends Base
{
    protected $table = 'product_category';
    public $primaryKey = ['product_id', 'category_id'];
    public $incrementing = false;
    public $timestamps = false;

    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id', 'id');
    }
}
