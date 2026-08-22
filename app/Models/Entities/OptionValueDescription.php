<?php

namespace App\Models\Entities;

use App\Models\Base\Base;

class OptionValueDescription extends Base
{
    protected $table = 'option_value_description';
    protected $primaryKey = ['option_value_id', 'language_code'];
    public $incrementing = false;
    public $timestamps = true;
    protected $fillable = [
        'option_value_id',
        'language_code',
        'name',
    ];
}
