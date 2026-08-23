<?php

namespace App\Models\Entities;

use App\Models\Entities\CarrierOrderStatus;
use App\Models\Base\Base;
use Illuminate\Database\Eloquent\SoftDeletes;

class Carrier extends Base
{
    use SoftDeletes;
    protected $table = 'carrier';
    protected $primaryKeyAutoIncrement = 'id';
    public $incrementing = true;
    public $timestamps = true;
    protected static array $destroyRelations = ['carrierOrderStatus'];
    protected $fillable = [
        'code',
        'name',
        'image',
        'sort_order',
    ];

    public function carrierOrderStatus()
    {
        return $this->hasMany(CarrierOrderStatus::class, 'carrier_id', 'id');
    }
}
