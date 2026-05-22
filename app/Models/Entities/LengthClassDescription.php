<?php

namespace App\Models\Entities;

use App\Models\Base\Base;

class LengthClassDescription extends Base
{
    protected $table = 'length_class_description';
    protected $primaryKey = ['length_class_id', 'language_code'];
    public $incrementing = false;
    public $timestamps = true;
}
