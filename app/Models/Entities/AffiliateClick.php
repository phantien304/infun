<?php

namespace App\Models\Entities;

use App\Models\Base\Base;

class AffiliateClick extends Base
{
    protected $table = 'affiliate_click';
    protected $primaryKeyAutoIncrement = 'id';
    public $timestamps = false;

    public function affiliate()
    {
        return $this->belongsTo(Affiliate::class, 'affiliate_id', 'id');
    }

    public function affiliateLink()
    {
        return $this->belongsTo(AffiliateLink::class, 'affiliate_link_id', 'id');
    }
}
