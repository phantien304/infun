<?php

namespace App\Models\Entities;

use App\Models\Base\Base;

class CouponHistory extends Base
{
    protected $table = 'coupon_history';
    protected $primaryKeyAutoIncrement = 'id';
    public $incrementing = true;
    public $timestamps = true;

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
