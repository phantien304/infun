<?php

namespace App\Models\Entities;

use App\Models\Base\Base;

/**
 * Override % hoa hồng theo ngành hàng (1 rule / category, global).
 * Precedence khi tính commission (Phase 3):
 * affiliate.commission_rate (per-KOL) > rule theo category của item >
 * config_affiliate_commission_rate. Tính theo TỪNG item trong đơn.
 */
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
