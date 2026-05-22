<?php

namespace App\Models\Entities;

use App\Models\Base\Base;

class BannerDescription extends Base
{
    protected $table = 'banner_description';
    protected $primaryKey = ['banner_id', 'language_code'];
    public $incrementing = false;
    public $timestamps = true;
}
