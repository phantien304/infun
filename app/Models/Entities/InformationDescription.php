<?php

namespace App\Models\Entities;

use App\Models\Base\Base;

class InformationDescription extends Base
{
    protected $table = 'information_description';
    public $incrementing = false;
    public $timestamps = true;
    public $primaryKey = ['information_id', 'language_code'];
}
