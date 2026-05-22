<?php

namespace App\Models\Entities;

use App\Models\Base\Base;

class VoucherThemeDescription extends Base
{
    protected $table = 'voucher_theme_description';
    protected $primaryKey = ['voucher_theme_id', 'language_code'];
    public $incrementing = false;
    public $timestamps = true;
}
