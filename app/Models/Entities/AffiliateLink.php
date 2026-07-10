<?php

namespace App\Models\Entities;

use App\Models\Base\Base;

class AffiliateLink extends Base
{
    protected $table = 'affiliate_link';
    protected $primaryKeyAutoIncrement = 'id';
    public $timestamps = true;

    public function affiliate()
    {
        return $this->belongsTo(Affiliate::class, 'affiliate_id', 'id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }
}
