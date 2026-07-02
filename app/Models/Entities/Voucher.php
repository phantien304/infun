<?php

namespace App\Models\Entities;

use App\Models\Base\Base;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class Voucher extends Base
{
    use SoftDeletes;

    protected $table = 'voucher';
    protected $primaryKeyAutoIncrement = 'id';
    public $incrementing = true;
    public $timestamps = true;
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

    public function voucherHistories(): HasMany
    {
        return $this->hasMany(VoucherHistory::class, 'voucher_id', 'id');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(VoucherHistory::class, 'voucher_id', 'id');
    }

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

    public function availableBalance(): float
    {
        return max(0.0, (float) $this->amount - (float) $this->redeemed_balance);
    }
}
