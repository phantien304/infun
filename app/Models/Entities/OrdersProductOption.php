<?php

namespace App\Models\Entities;

use App\Models\Base\Base;

class OrdersProductOption extends Base
{
    protected $table = 'orders_product_option';
    protected $primaryKeyAutoIncrement = 'id';
    public $timestamps = true;
    protected $fillable = [
        'order_id', 'order_product_id', 'product_option_id', 'product_option_value_id',
        'image', 'name', 'value', 'children', 'type', 'variation', 'required',
    ];
}
