<?php

namespace App\Models\Entities;

use App\Models\Base\Base;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Master gift campaign — xem schema chi tiết ở migration
 * `2026_06_12_000000_create_gift_table.php`.
 *
 * Code đọc enum qua `getCoreConfig('gift.trigger_type.*')` /
 * `getCoreConfig('gift.pick_type.*')` — KHÔNG hardcode literal 0/1/2.
 */
class Gift extends Base
{
    use SoftDeletes;

    protected $table = 'gift';
    protected $primaryKeyAutoIncrement = 'id';
    public $incrementing = true;
    public $timestamps = true;

    /** Bypass HasSchemaCache fillable forever-cache (xem note Coupon::$guarded). */
    protected $guarded = [];

    protected $casts = [
        'trigger_type' => 'integer',
        'min_subtotal' => 'float',
        'pick_type'    => 'integer',
        'pick_limit'   => 'integer',
        'uses_total'   => 'integer',
        'used_count'   => 'integer',
        'is_active'    => 'integer',
        'sort_order'   => 'integer',
        'date_start'   => 'datetime',
        'date_end'     => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(GiftItem::class, 'gift_id', 'id')->orderBy('sort_order');
    }

    public function triggerProducts(): HasMany
    {
        return $this->hasMany(GiftTriggerProduct::class, 'gift_id', 'id');
    }

    public function orderGifts(): HasMany
    {
        return $this->hasMany(OrderGift::class, 'gift_id', 'id');
    }

    /**
     * Active = is_active=1 + date trong window + chưa hết quota global.
     * KHÔNG check trigger ở đây (cần cart context — GiftService::validate).
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query
            ->where("{$this->getTable()}.is_active", 1)
            ->dateStartToEnd()
            ->where(function (Builder $q) {
                $q->whereNull("{$this->getTable()}.uses_total")
                    ->orWhereColumn(
                        "{$this->getTable()}.used_count",
                        '<',
                        "{$this->getTable()}.uses_total",
                    );
            });
    }
}
