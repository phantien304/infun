<?php

namespace App\Models\Entities;

use App\Models\Base\Base;

class StockStatusDescription extends Base
{
    protected $table = 'stock_status_description';
    public $primaryKey = ['stock_status_id', 'language_code'];
    public $incrementing = false;
    public $timestamps = true;
    // Fillable thật (xem docs/SCHEMA-CACHE-FILLABLE.md).
    protected $fillable = ['stock_status_id', 'language_code', 'name'];

    public function stockStatus()
    {
        return $this->belongsTo(StockStatus::class, 'stock_status_id', 'id');
    }
}
