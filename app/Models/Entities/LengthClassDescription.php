<?php

namespace App\Models\Entities;

use App\Models\Base\Base;

class LengthClassDescription extends Base
{
    protected $table = 'length_class_description';
    protected $primaryKeyAutoIncrement = 'id';
    public $timestamps = true;
}
