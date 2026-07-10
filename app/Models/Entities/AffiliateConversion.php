<?php

namespace App\Models\Entities;

use App\Models\Base\Base;

class AffiliateConversion extends Base
{
    protected $table = 'affiliate_conversion';
    protected $primaryKeyAutoIncrement = 'id';
    public $timestamps = true;
    protected $casts = [
        'commission_rate' => 'decimal:2',
        'approved_at'     => 'datetime',
    ];

    public function affiliate()
    {
        return $this->belongsTo(Affiliate::class, 'affiliate_id', 'id');
    }

    public function order()
    {
        return $this->belongsTo(Orders::class, 'order_id', 'id');
    }

    public function click()
    {
        return $this->belongsTo(AffiliateClick::class, 'click_id', 'id');
    }

    public function payout()
    {
        return $this->belongsTo(AffiliatePayout::class, 'payout_id', 'id');
    }
}
