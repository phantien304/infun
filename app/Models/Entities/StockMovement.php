<?php

namespace App\Models\Entities;

use App\Enums\StockMovementType;
use App\Models\Base\Base;

class StockMovement extends Base
{
    protected $table = 'stock_movement';
    protected $primaryKeyAutoIncrement = 'id';
    public $timestamps = false;

    protected $fillable = [
        'product_variant_id',
        'warehouse_id',
        'type',
        'quantity_change',
        'on_hand_after',
        'reference_type',
        'reference_id',
        'user_id',
        'note',
    ];

    protected $casts = [
        'type'            => StockMovementType::class,
        'quantity_change' => 'integer',
        'on_hand_after'   => 'integer',
    ];

    public function productVariant()
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id', 'id');
    }
}
