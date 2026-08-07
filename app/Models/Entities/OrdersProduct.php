<?php

namespace App\Models\Entities;

use App\Models\Base\Base;

class OrdersProduct extends Base
{
    protected $table = 'orders_product';
    protected $primaryKeyAutoIncrement = 'id';
    public $timestamps = true;
    protected static array $destroyRelations = ['ordersProductOptions'];

    // Khai báo thật 2026-08-03 — xem docs/SCHEMA-CACHE-FILLABLE.md.
    protected $fillable = [
        'order_id', 'product_id', 'product_variant_id', 'name', 'model',
        'quantity', 'price', 'total', 'tax', 'reward',
    ];

    public function ordersProductOptions()
    {
        return $this->hasMany(OrdersProductOption::class, 'order_product_id', 'id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }
}
