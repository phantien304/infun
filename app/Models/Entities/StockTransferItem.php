<?php

namespace App\Models\Entities;

use App\Models\Base\Base;

class StockTransferItem extends Base
{
    protected $table = 'stock_transfer_item';
    protected $primaryKeyAutoIncrement = 'id';
    public $timestamps = true;

    protected $fillable = [
        'stock_transfer_id',
        'product_variant_id',
        'quantity',
        'received_quantity',
    ];

    protected $casts = [
        'quantity'          => 'integer',
        'received_quantity' => 'integer',
    ];

    public function stockTransfer()
    {
        return $this->belongsTo(StockTransfer::class, 'stock_transfer_id', 'id');
    }

    public function productVariant()
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id', 'id');
    }
}
