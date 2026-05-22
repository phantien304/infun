<?php

namespace App\Models\Entities;

use App\Models\Base\Base;

class OrdersProductOption extends Base
{
    protected $table = 'orders_product_option';
    protected $primaryKeyAutoIncrement = 'id';
    public $timestamps = true;

    public function productOption()
    {
        return $this->belongsTo(ProductOption::class, 'product_option_id', 'id');
    }
}
