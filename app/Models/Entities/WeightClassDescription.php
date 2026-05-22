<?php

namespace App\Models\Entities;

use App\Models\Base\Base;

class WeightClassDescription extends Base
{
    protected $table = 'weight_class_description';
    protected $primaryKey = ['weight_class_id', 'language_code'];
    public $incrementing = false;
    public $timestamps = true;
}
