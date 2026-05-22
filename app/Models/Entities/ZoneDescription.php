<?php

namespace App\Models\Entities;

use App\Models\Base\Base;

class ZoneDescription extends Base
{
    protected $table = 'zone_description';
    protected $primaryKey = ['zone_id', 'language_code'];
    public $incrementing = false;
    public $timestamps = true;

    public function zone()
    {
        $this->belongsTo(Zone::class, 'id', 'zone_id');
    }
}
