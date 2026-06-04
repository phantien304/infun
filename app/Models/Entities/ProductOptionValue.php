<?php

namespace App\Models\Entities;

use App\Models\Base\Base;

class ProductOptionValue extends Base
{
    protected $table = 'product_option_value';
    protected $primaryKeyAutoIncrement = 'id';
    public $timestamps = true;

    public function optionValue()
    {
        return $this->belongsTo(OptionValue::class, 'option_value_id', 'id');
    }

    public function productOption()
    {
        return $this->belongsTo(
            ProductOption::class,
            ['product_id', 'option_id'],
            ['product_id', 'option_id'],
        );
    }
}
