<?php

namespace App\Models\Entities;

use App\Models\Base\Base;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Voucher = thẻ quà tặng cá nhân (gift card), KHÔNG phải coupon marketing.
 *
 * Status (enum int):
 *   1 = active      → còn dùng được (balance > 0, chưa expire)
 *   2 = expired     → date_expire < now (sweeper cron flip)
 *   3 = fully_used  → redeemed_balance >= amount
 *   4 = revoked     → admin thu hồi (fraud)
 *
 * Số dư khả dụng = amount - redeemed_balance. Denormalize redeemed_balance để
 * tránh SUM(voucher_history) mỗi request — observer cập nhật khi history
 * confirmed/refunded.
 *
 * KHÔNG hardcode literal — đọc qua `getCoreConfig('voucher.status.*')`.
 */
class Voucher extends Base
{
    use SoftDeletes;

    protected $table = 'voucher';
    protected $primaryKeyAutoIncrement = 'id';
    public $incrementing = true;
    public $timestamps = true;

    /** Bypass HasSchemaCache fillable (xem note Coupon::$guarded). */
    protected $guarded = [];

    protected $casts = [
        'order_id'         => 'integer',
        'voucher_theme_id' => 'integer',
        'amount'           => 'float',
        'redeemed_balance' => 'float',
        'status'           => 'integer',
        'date_expire'      => 'date',
        'sent_at'          => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Orders::class, 'order_id', 'id');
    }

    public function voucherTheme(): BelongsTo
    {
        return $this->belongsTo(VoucherTheme::class, 'voucher_theme_id', 'id');
    }

    /** Backward-compat alias — code legacy còn gọi `voucherHistories`. */
    public function voucherHistories(): HasMany
    {
        return $this->hasMany(VoucherHistory::class, 'voucher_id', 'id');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(VoucherHistory::class, 'voucher_id', 'id');
    }

    // === Scopes ===

    /**
     * Redeemable = status=active + chưa expire + còn balance.
     */
    public function scopeRedeemable(Builder $query): Builder
    {
        $statusActive = (int) getCoreConfig('voucher.status.active');
        $now = Carbon::now();
        return $query
            ->where("{$this->getTable()}.status", $statusActive)
            ->where(function (Builder $q) use ($now) {
                $q->whereNull("{$this->getTable()}.date_expire")
                    ->orWhere("{$this->getTable()}.date_expire", '>=', $now->toDateString());
            })
            ->whereColumn(
                "{$this->getTable()}.redeemed_balance",
                '<',
                "{$this->getTable()}.amount",
            );
    }

    public function scopeForEmail(Builder $query, string $email): Builder
    {
        return $query->where("{$this->getTable()}.to_email", $email);
    }

    /**
     * Số dư khả dụng (amount - redeemed_balance). Clamp ≥ 0 phòng data drift.
     */
    public function availableBalance(): float
    {
        return max(0.0, (float) $this->amount - (float) $this->redeemed_balance);
    }
}
