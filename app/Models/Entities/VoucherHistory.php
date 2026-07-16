<?php

namespace App\Models\Entities;

use App\Enums\VoucherHistoryStatus;
use App\Models\Base\Base;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VoucherHistory extends Base
{
    protected $table = 'voucher_history';
    protected $primaryKeyAutoIncrement = 'id';
    public $incrementing = true;
    public $timestamps = true;

    protected $guarded = [];

    protected $casts = [
        'voucher_id' => 'integer',
        'order_id'   => 'integer',
        'user_id'    => 'integer',
        'amount'     => 'float',
        'status'     => 'integer',
    ];

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class, 'voucher_id', 'id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Orders::class, 'order_id', 'id');
    }

    public function scopeForVoucher(Builder $query, int $voucherId): Builder
    {
        return $query->where('voucher_id', $voucherId);
    }

    public function scopeForOrder(Builder $query, int $orderId): Builder
    {
        return $query->where('order_id', $orderId);
    }

    public function scopeConfirmed(Builder $query): Builder
    {
        return $query->where('status', VoucherHistoryStatus::Confirmed->value);
    }
}
