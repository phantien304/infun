<?php

namespace App\Models\Entities;

use App\Models\Base\Base;

class Affiliate extends Base
{
    protected $table = 'affiliate';
    protected $primaryKeyAutoIncrement = 'id';
    public $timestamps = true;
    protected $casts = [
        'commission_rate' => 'decimal:2',
        'payment_info'    => 'array',
        'approved_at'     => 'datetime',
    ];
    protected static array $destroyRelations = [
        'affiliateLinks', 'affiliateClicks', 'affiliateConversions', 'affiliatePayouts',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function affiliateLinks()
    {
        return $this->hasMany(AffiliateLink::class, 'affiliate_id', 'id');
    }

    public function affiliateClicks()
    {
        return $this->hasMany(AffiliateClick::class, 'affiliate_id', 'id');
    }

    public function affiliateConversions()
    {
        return $this->hasMany(AffiliateConversion::class, 'affiliate_id', 'id');
    }

    public function affiliatePayouts()
    {
        return $this->hasMany(AffiliatePayout::class, 'affiliate_id', 'id');
    }

    public function coupons()
    {
        return $this->belongsToMany(Coupon::class, 'affiliate_coupon', 'affiliate_id', 'coupon_id');
    }
}
