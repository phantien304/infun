<?php

namespace App\Models\Entities;

use App\Models\Base\Base;

class AttributeDescription extends Base
{
    protected $table = 'attribute_description';
    public $incrementing = false;
    public $timestamps = true;
    public $primaryKey = ['attribute_id', 'language_code'];
}
