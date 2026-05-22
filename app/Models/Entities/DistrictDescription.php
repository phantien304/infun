<?php

namespace App\Models\Entities;

use App\Models\Base\Base;

class DistrictDescription extends Base
{
    protected $table = 'district_description';
    protected $primaryKey = ['district_id', 'language_code'];
    public $incrementing = false;
    public $timestamps = true;

    public function district()
    {
        $this->belongsTo(District::class, 'id', 'district_id');
    }
}
