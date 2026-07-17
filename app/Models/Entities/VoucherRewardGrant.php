<?php

namespace App\Models\Entities;

use App\Models\Base\Base;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VoucherRewardGrant extends Base
{
    protected $table = 'voucher_reward_grant';
    protected $primaryKeyAutoIncrement = 'id';
    public $incrementing = true;
    public $timestamps = true;
    protected $guarded = [];

    protected $casts = [
        'rule_id'    => 'integer',
        'order_id'   => 'integer',
        'user_id'    => 'integer',
        'voucher_id' => 'integer',
    ];

    public function rule(): BelongsTo
    {
        return $this->belongsTo(VoucherRewardRule::class, 'rule_id', 'id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Orders::class, 'order_id', 'id');
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class, 'voucher_id', 'id');
    }
}
