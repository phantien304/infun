<?php

namespace App\Models\Entities;

use App\Models\Base\Base;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pivot "Mã của tôi" Shopee — user lưu voucher trước, dùng sau.
 *
 * Composite PK (user_id, coupon_id) — không có cột `id` riêng. Eloquent phải
 * khai báo `$incrementing = false` và compoships để query composite.
 *
 * Không có cột timestamps chuẩn (`created_at`/`updated_at`) — chỉ `saved_at`
 * DEFAULT CURRENT_TIMESTAMP. Tắt `$timestamps`.
 */
class UserCoupon extends Base
{
    protected $table = 'user_coupon';
    public $timestamps = false;

    public $primaryKey = ['user_id', 'coupon_id'];
    public $incrementing = false;

    /** Bypass schema cache (bảng mới, tránh stale fillable). */
    protected $guarded = [];

    protected $casts = [
        'user_id'   => 'integer',
        'coupon_id' => 'integer',
        'saved_at'  => 'datetime',
    ];

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class, 'coupon_id', 'id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
