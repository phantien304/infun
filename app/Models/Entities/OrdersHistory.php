<?php

namespace App\Models\Entities;

use App\Models\Base\Base;

class OrdersHistory extends Base
{
    protected $table = 'orders_history';
    protected $primaryKeyAutoIncrement = 'id';
    public $timestamps = true;
    protected $fillable = ['order_id', 'order_status_id', 'user_id', 'notify', 'comment'];

    public function ordersStatus()
    {
        return $this->belongsTo(OrdersStatus::class, 'order_status_id', 'id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
