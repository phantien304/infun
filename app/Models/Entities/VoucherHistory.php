<?php

namespace App\Models\Entities;

use App\Models\Base\Base;

class VoucherHistory extends Base
{
    protected $table = 'voucher_history';
    protected $primaryKeyAutoIncrement = 'id';
    public $incrementing = true;
    public $timestamps = true;
}
