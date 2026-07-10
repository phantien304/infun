<?php

namespace App\Models\Entities;

use App\Models\Base\Base;

class AffiliatePayout extends Base
{
    protected $table = 'affiliate_payout';
    protected $primaryKeyAutoIncrement = 'id';
    public $timestamps = true;
    protected $casts = ['paid_at' => 'datetime'];

    public function affiliate()
    {
        return $this->belongsTo(Affiliate::class, 'affiliate_id', 'id');
    }

    public function affiliateConversions()
    {
        return $this->hasMany(AffiliateConversion::class, 'payout_id', 'id');
    }
}
