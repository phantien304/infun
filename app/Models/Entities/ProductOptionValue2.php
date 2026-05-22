<?php

namespace App\Models\Entities;

use App\Models\Base\Base;

class ProductOptionValue2 extends Base
{
    protected $table = 'product_option_value_2';
    protected $primaryKeyAutoIncrement = 'id';
    public $timestamps = true;

    public function optionValue()
    {
        return $this->belongsTo(OptionValue::class, 'option_value_2_id', 'id');
    }
}
