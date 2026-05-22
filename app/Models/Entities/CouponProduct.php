<?php

namespace App\Models\Entities;

use App\Models\Base\Base;

class CouponProduct extends Base
{
    protected $table = 'coupon_product';
    protected $primaryKey = ['coupon_id', 'product_id'];
    public $incrementing = false;
    public $timestamps = false;

    public function product()
    {
        return $this->belongsTo(Product::class, 'id', 'product_id');
    }

    public function coupon()
    {
        return $this->belongsTo(Coupon::class, 'id', 'coupon_id');
    }
}
