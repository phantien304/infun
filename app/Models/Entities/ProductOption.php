<?php

namespace App\Models\Entities;

use App\Models\Base\Base;

class ProductOption extends Base
{
    protected $table = 'product_option';
    protected $primaryKeyAutoIncrement = 'id';
    public $timestamps = true;
    protected static $_destroyRelations = ['productOptionValues'];

    public function option()
    {
        return $this->belongsTo(Option::class, 'option_id', 'id');
    }

    public function productOptionValues()
    {
        return $this->hasMany(ProductOptionValue::class, 'product_option_id', 'id');
    }
}
