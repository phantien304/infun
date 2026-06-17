<?php

namespace App\Models\Entities;

use App\Models\Base\Base;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GiftTriggerProduct extends Base
{
    protected $table = 'gift_trigger_product';
    public $primaryKey = ['gift_id', 'product_id'];
    public $incrementing = false;
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'gift_id'    => 'integer',
        'product_id' => 'integer',
    ];

    public function gift(): BelongsTo
    {
        return $this->belongsTo(Gift::class, 'gift_id', 'id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }
}
