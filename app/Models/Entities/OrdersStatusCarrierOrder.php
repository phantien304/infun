<?php

namespace App\Models\Entities;

use App\Models\Base\Base;

class OrdersStatusCarrierOrder extends Base
{
    protected $table = 'orders_status_carrier_order';
    public $primaryKey = ['carrier_order_status_id', 'language_code'];
    public $incrementing = false;
    public $timestamps = false;

    public function carrierOrderStatus()
    {
        return $this->belongsTo(CarrierOrderStatus::class, 'carrier_order_status_id', 'id');
    }
}
