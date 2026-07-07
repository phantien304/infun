<?php

namespace App\Models\Entities;

use App\Models\Base\Base;

class WeightClassDescription extends Base
{
    protected $table = 'weight_class_description';
    protected $primaryKeyAutoIncrement = 'id';
    public $timestamps = true;
}
