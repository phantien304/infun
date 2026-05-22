<?php

namespace App\Models\Entities;

use App\Models\Base\Base;

class OrdersOption extends Base
{
    protected $table = 'orders_option';
    protected $primaryKeyAutoIncrement = 'id';
    public $timestamps = true;
}
