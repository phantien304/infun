<?php

namespace App\Models\Entities;

use App\Models\Base\Base;

class ProductOptionValue extends Base
{
    protected $table = 'product_option_value';
    protected $primaryKeyAutoIncrement = 'id';
    public $timestamps = true;
    protected static $_destroyRelations = ['productOptionValues2'];

    public function productOptionValues2()
    {
        return $this->hasMany(ProductOptionValue2::class, 'product_option_value_id', 'id');
    }

    public function optionValue()
    {
        return $this->belongsTo(OptionValue::class, 'option_value_1_id', 'id');
    }
}
