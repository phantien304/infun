<?php

namespace App\Models\Entities;

use App\Models\Base\Base;
use Illuminate\Database\Eloquent\SoftDeletes;

class Coupon extends Base
{
    use SoftDeletes;
    protected $table = 'coupon';
    protected $primaryKeyAutoIncrement = 'id';
    public $incrementing = true;
    public $timestamps = true;
    protected static $_destroyRelations = ['couponProducts', 'couponCategories'];

    public function couponProducts()
    {
        return $this->hasMany(CouponProduct::class, 'coupon_id', 'id');
    }

    public function couponCategories()
    {
        return $this->hasMany(CouponCategory::class, 'coupon_id', 'id');
    }

    public function couponHistories()
    {
        return $this->hasMany(CouponHistory::class, 'coupon_id', 'id');
    }
}
