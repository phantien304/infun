<?php

namespace App\Models\Entities;

use App\Models\Base\Base;

class OrdersVoucher extends Base
{
    protected $table = 'order_voucher';
    protected $primaryKeyAutoIncrement = 'id';
    public $timestamps = true;
}
