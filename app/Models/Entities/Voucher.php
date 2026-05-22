<?php

namespace App\Models\Entities;

use App\Models\Base\Base;
use Illuminate\Database\Eloquent\SoftDeletes;

class Voucher extends Base
{
    use SoftDeletes;
    protected $table = 'voucher';
    protected $primaryKeyAutoIncrement = 'id';
    public $incrementing = true;
    public $timestamps = true;

    public function order()
    {
        return $this->belongsTo(Orders::class, 'order_id', 'id');
    }

    public function voucherTheme()
    {
        return $this->belongsTo(VoucherTheme::class, 'voucher_theme_id', 'id');
    }

    public function voucherHistories()
    {
        return $this->hasMany(VoucherHistory::class, 'voucher_id', 'id');
    }
}
