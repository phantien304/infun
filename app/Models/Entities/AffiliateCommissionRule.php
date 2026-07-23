<?php

namespace App\Models\Entities;

use App\Models\Base\Base;

class AffiliateCommissionRule extends Base
{
    protected $table = 'affiliate_commission_rule';
    protected $primaryKeyAutoIncrement = 'id';
    public $timestamps = true;
    protected $casts = ['rate' => 'decimal:2'];

    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id', 'id');
    }
}
