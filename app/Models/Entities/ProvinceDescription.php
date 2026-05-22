<?php

namespace App\Models\Entities;

use App\Models\Base\Base;

class ProvinceDescription extends Base
{
    protected $table = 'province_description';
    protected $primaryKey = ['province_id', 'language_code'];
    public $incrementing = false;
    public $timestamps = true;

    public function province()
    {
        $this->belongsTo(Province::class, 'id', 'province_id');
    }
}
