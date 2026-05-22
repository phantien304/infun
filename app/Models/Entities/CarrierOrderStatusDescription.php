<?php

namespace App\Models\Entities;

use App\Models\Base\Base;

class CarrierOrderStatusDescription extends Base
{
    protected $table = 'carrier_order_status_description';
    public $primaryKey = ['carrier_order_status_id', 'language_code'];
    public $incrementing = false;
    public $timestamps = true;
}
