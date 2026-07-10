<?php

namespace App\Models\Entities;

use App\Models\Base\Base;
use Illuminate\Database\Eloquent\SoftDeletes;

class Warehouse extends Base
{
    use SoftDeletes;

    protected $table = 'warehouse';
    protected $primaryKeyAutoIncrement = 'id';
    public $timestamps = true;

    protected $casts = [
        'priority'    => 'integer',
        'is_active'   => 'boolean',
        'is_sellable' => 'boolean',
    ];

    public function productStocks()
    {
        return $this->hasMany(ProductStock::class, 'warehouse_id', 'id');
    }

    public function scopeSellable($query)
    {
        return $query->where('is_active', true)->where('is_sellable', true);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
