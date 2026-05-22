<?php

namespace App\Models\Entities;

use App\Models\Base\Base;

class BannerValueDescription extends Base
{
    protected $table = 'banner_value_description';
    protected $primaryKey = ['banner_value_id', 'language_code'];
    public $incrementing = false;
    public $timestamps = true;
}
