<?php

namespace App\Models\Entities;

use App\Models\Base\Base;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Lifecycle:
 *   0 = applied    → đang trong cart, order chưa thanh toán (KHÔNG trừ quota
 *                    thật, chỉ giữ chỗ tránh user khác dùng vượt mức)
 *   1 = used       → order paid → trừ quota
 *   2 = cancelled  → order cancel → trả quota lại
 *
 * Khi user áp coupon trong cart: insert row status=0.
 * Khi order paid: UPDATE status=1 + `coupon.used_count++` qua observer.
 * Khi order cancel: UPDATE status=2 + `coupon.used_count--` qua observer.
 *
 * Cron clean status=0 stale (> coupon.cart_applied_ttl_minutes) để giải phóng
 * quota cho user khác (handle abandoned cart).
 */
class CouponHistory extends Base
{
    protected $table = 'coupon_history';
    protected $primaryKeyAutoIncrement = 'id';
    public $incrementing = true;
    public $timestamps = true;

    /** Bypass schema cache (xem note ở Coupon::$guarded). */
    protected $guarded = [];

    protected $casts = [
        'coupon_id' => 'integer',
        'order_id'  => 'integer',
        'user_id'   => 'integer',
        'amount'    => 'float',
        'status'    => 'integer',
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
        return $query->whereIn('status', [
            getCoreConfig('coupon.history_status.applied'),
            getCoreConfig('coupon.history_status.used'),
        ]);
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
