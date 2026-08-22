<?php

namespace App\Models\Entities;

use App\Models\Base\Base;

class OptionDescription extends Base
{
    protected $table = 'option_description';
    protected $primaryKey = ['option_id', 'language_code'];
    public $incrementing = false;
    public $timestamps = true;
    protected $fillable = [
        'option_id',
        'language_code',
        'name',
        'name_display',
    ];
}
