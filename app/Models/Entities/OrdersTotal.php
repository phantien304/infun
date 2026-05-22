<?php

namespace App\Models\Entities;

use App\Models\Base\Base;

class OrdersTotal extends Base
{
    protected $table = 'orders_total';
    protected $primaryKeyAutoIncrement = 'id';
    public $timestamps = true;
}
