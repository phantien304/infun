<?php

namespace App\Models\Entities;

use App\Models\Base\Base;

class ProductStock extends Base
{
    public const DEFAULT_WAREHOUSE_ID = 1;

    protected $table = 'product_stock';
    protected $primaryKeyAutoIncrement = 'id';
    public $timestamps = true;

    protected $casts = [
        'on_hand'  => 'integer',
        'reserved' => 'integer',
        'subtract' => 'boolean',
        'version'  => 'integer',
    ];

    public function productVariant()
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id', 'id');
    }

    public function getAvailableAttribute(): int
    {
        return max(0, (int) $this->on_hand - (int) $this->reserved);
    }
}
