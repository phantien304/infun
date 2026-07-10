<?php

namespace App\Models\Entities;

use App\Enums\StockTransferStatus;
use App\Models\Base\Base;

class StockTransfer extends Base
{
    protected $table = 'stock_transfer';
    protected $primaryKeyAutoIncrement = 'id';
    public $timestamps = true;

    protected $fillable = [
        'code',
        'from_warehouse_id',
        'to_warehouse_id',
        'status',
        'note',
        'created_by',
        'shipped_at',
        'received_at',
    ];

    protected $casts = [
        'status'      => StockTransferStatus::class,
        'shipped_at'  => 'datetime',
        'received_at' => 'datetime',
    ];

    public function fromWarehouse()
    {
        return $this->belongsTo(Warehouse::class, 'from_warehouse_id', 'id');
    }

    public function toWarehouse()
    {
        return $this->belongsTo(Warehouse::class, 'to_warehouse_id', 'id');
    }

    public function items()
    {
        return $this->hasMany(StockTransferItem::class, 'stock_transfer_id', 'id');
    }
}
