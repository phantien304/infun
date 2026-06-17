<?php

namespace App\Models\Entities;

use App\Models\Base\Base;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GiftItem extends Base
{
    protected $table = 'gift_item';
    protected $primaryKeyAutoIncrement = 'id';
    public $incrementing = true;
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'gift_id'            => 'integer',
        'product_id'         => 'integer',
        'product_variant_id' => 'integer',
        'quantity'           => 'integer',
        'sort_order'         => 'integer',
    ];

    public function gift(): BelongsTo
    {
        return $this->belongsTo(Gift::class, 'gift_id', 'id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id', 'id');
    }
}
