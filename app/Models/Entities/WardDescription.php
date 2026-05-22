<?php

namespace App\Models\Entities;

use App\Models\Base\Base;

class WardDescription extends Base
{
    protected $table = 'ward_description';
    protected $primaryKey = ['ward_id', 'language_code'];
    public $incrementing = false;
    public $timestamps = true;

    public function province()
    {
        $this->belongsTo(Ward::class, 'id', 'ward_id');
    }
}
