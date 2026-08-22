<?php

namespace App\Models\Entities;

use App\Models\Base\Base;

class AttributeValueDescription extends Base
{
    protected $table = 'attribute_value_description';
    protected $primaryKey = ['attribute_value_id', 'language_code'];
    public $incrementing = false;
    public $timestamps = true;
    protected $fillable = [
        'attribute_value_id',
        'language_code',
        'name',
    ];
}
