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
    protected $fillable = [
        'code', 'name', 'address', 'zone_id', 'district_id', 'ward_id',
        'telephone', 'priority', 'is_active', 'is_sellable',
        'deleted_at',
    ];

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
