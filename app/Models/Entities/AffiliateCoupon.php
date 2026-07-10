<?php

namespace App\Models\Entities;

use App\Models\Base\Base;

/** Pivot affiliate ↔ coupon (composite PK, không timestamps — như ProductReward). */
class AffiliateCoupon extends Base
{
    protected $table = 'affiliate_coupon';
    protected $primaryKey = ['affiliate_id', 'coupon_id'];
    public $incrementing = false;
    public $timestamps = false;

    public function affiliate()
    {
        return $this->belongsTo(Affiliate::class, 'affiliate_id', 'id');
    }

    public function coupon()
    {
        return $this->belongsTo(Coupon::class, 'coupon_id', 'id');
    }
}
