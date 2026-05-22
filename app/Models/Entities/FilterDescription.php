<?php

namespace App\Models\Entities;

use App\Models\Base\Base;

class FilterDescription extends Base
{
    protected $table = 'filter_description';
    protected $primaryKey = ['filter_id', 'language_code'];
    public $incrementing = false;
    public $timestamps = true;
}
