<?php

namespace App\Models\Entities;

use App\Models\Base\Base;

class CouponCategory extends Base
{
    protected $table = 'coupon_category';
    protected $primaryKey = ['coupon_id', 'category_id'];
    public $incrementing = false;
    public $timestamps = false;

    public function coupon()
    {
        return $this->belongsTo(Coupon::class, 'id', 'coupon_id');
    }

    public function category()
    {
        return $this->belongsTo(Category::class, 'id', 'category_id');
    }
}
