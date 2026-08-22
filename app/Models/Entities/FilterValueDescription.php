<?php

namespace App\Models\Entities;

use App\Models\Base\Base;

class FilterValueDescription extends Base
{
    protected $table = 'filter_value_description';
    protected $primaryKey = ['filter_value_id', 'language_code'];
    public $incrementing = false;
    public $timestamps = true;
    protected $fillable = [
        'filter_value_id',
        'language_code',
        'name',
    ];
}
