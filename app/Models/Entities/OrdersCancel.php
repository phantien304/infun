<?php

namespace App\Models\Entities;

use App\Models\Base\Base;

class OrdersCancel extends Base
{
    protected $table = 'orders_cancel';
    protected $primaryKeyAutoIncrement = 'id';
    public $timestamps = true;
    protected $fillable = ['order_id', 'user_id', 'return_reason', 'comment'];
}
