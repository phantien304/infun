<?php

namespace App\Models\Entities;

use App\Models\Base\Base;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Audit gift claimed per order. Composite PK (order_id, gift_item_id).
 * CASCADE order delete → row tự dọn → quota global trả lại qua observer.
 */
class OrderGift extends Base
{
    protected $table = 'order_gift';
    public $primaryKey = ['order_id', 'gift_item_id'];
    public $incrementing = false;
    public $timestamps = true;

    protected $guarded = [];

    protected $casts = [
        'order_id'     => 'integer',
        'gift_id'      => 'integer',
        'gift_item_id' => 'integer',
        'quantity'     => 'integer',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Orders::class, 'order_id', 'id');
    }

    public function gift(): BelongsTo
    {
        return $this->belongsTo(Gift::class, 'gift_id', 'id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(GiftItem::class, 'gift_item_id', 'id');
    }

    public function scopeForOrder(Builder $query, int $orderId): Builder
    {
        return $query->where('order_id', $orderId);
    }
}
