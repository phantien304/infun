<?php

namespace App\Models\Entities;

use App\Models\Base\Base;

class OrdersStatusLog extends Base
{
    protected $table = 'orders_status_log';
    protected $primaryKeyAutoIncrement = 'id';
    public $timestamps = true;

    public function ordersStatus()
    {
        return $this->belongsTo(OrdersStatus::class, 'order_status_id', 'id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
