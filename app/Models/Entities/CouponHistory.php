<?php

namespace App\Models\Entities;

use App\Enums\CouponHistoryStatus;
use App\Models\Base\Base;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CouponHistory extends Base
{
    protected $table = 'coupon_history';
    protected $primaryKeyAutoIncrement = 'id';
    public $incrementing = true;
    public $timestamps = true;

    protected $guarded = [];

    protected $casts = [
        'coupon_id' => 'integer',
        'order_id'  => 'integer',
        'user_id'   => 'integer',
        'amount'    => 'float',
        'status'    => CouponHistoryStatus::class,
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class, 'coupon_id', 'id');
    }

    public function scopeUsedOrApplied(Builder $query): Builder
    {
        return $query->whereIn('status', CouponHistoryStatus::countedTowardUsage());
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForCoupon(Builder $query, int $couponId): Builder
    {
        return $query->where('coupon_id', $couponId);
    }
}
