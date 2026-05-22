<?php

namespace App\Models\Entities;

use App\Models\Base\Base;

class OrdersProduct extends Base
{
    protected $table = 'orders_product';
    protected $primaryKeyAutoIncrement = 'id';
    public $timestamps = true;
    protected static $destroyRelations = ['ordersProductOptions'];

    public function ordersProductOptions()
    {
        return $this->hasMany(OrdersProductOption::class, 'order_product_id', 'id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }
}
