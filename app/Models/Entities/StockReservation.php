<?php

namespace App\Models\Entities;

use App\Models\Base\Base;

class StockReservation extends Base
{
    protected $table = 'stock_reservation';
    protected $primaryKeyAutoIncrement = 'id';
    public $timestamps = true;

    protected $fillable = [
        'holder',
        'user_id',
        'product_variant_id',
        'warehouse_id',
        'quantity',
        'expires_at',
    ];

    protected $casts = [
        'user_id'            => 'integer',
        'product_variant_id' => 'integer',
        'warehouse_id'       => 'integer',
        'quantity'           => 'integer',
        'expires_at'         => 'datetime',
    ];

    public function productVariant()
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id', 'id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
