<?php

namespace App\Models\Entities;

use App\Models\Base\Base;
use Illuminate\Database\Eloquent\SoftDeletes;

class CarrierOrderStatus extends Base
{
    use SoftDeletes;
    protected $table = 'carrier_order_status';
    protected $primaryKeyAutoIncrement = 'id';
    public $timestamps = true;
    protected static array $destroyRelations = ['ordersStatusCarrierOrders'];
    protected $fillable = [
        'carrier_id',
        'code',
        'name',
    ];

    public function description()
    {
        return $this->hasOne(CarrierOrderStatusDescription::class, 'carrier_order_status_id', 'id')->forLocale();
    }

    public function descriptions()
    {
        return $this->hasMany(CarrierOrderStatusDescription::class, 'carrier_order_status_id', 'id');
    }

    public function ordersStatusCarrierOrder()
    {
        return $this->belongsTo(OrdersStatusCarrierOrder::class, 'id', 'carrier_order_status_id');
    }

    public function ordersStatusCarrierOrders()
    {
        return $this->hasMany(OrdersStatusCarrierOrder::class, 'carrier_order_status_id', 'id');
    }

    public function carrier()
    {
        return $this->belongsTo(Carrier::class, 'carrier_id', 'id');
    }
}
