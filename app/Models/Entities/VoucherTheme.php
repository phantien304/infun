<?php

namespace App\Models\Entities;

use App\Models\Base\Base;
use Illuminate\Database\Eloquent\SoftDeletes;

class VoucherTheme extends Base
{
    use SoftDeletes;
    protected $table = 'voucher_theme';
    protected $primaryKeyAutoIncrement = 'id';
    public $incrementing = true;
    public $timestamps = true;

    public function voucherThemeDescriptions()
    {
        return $this->hasMany(VoucherThemeDescription::class, 'voucher_theme_id', 'id');
    }

    public function voucherThemeDescription()
    {
        return $this->belongsTo(VoucherThemeDescription::class, 'id', 'voucher_theme_id');
    }
}
