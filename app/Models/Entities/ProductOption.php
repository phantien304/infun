<?php

namespace App\Models\Entities;

use App\Models\Base\Base;

class ProductOption extends Base
{
    protected $table = 'product_option';
    public $primaryKey = ['product_id', 'option_id'];
    public $incrementing = false;
    public $timestamps = true;
    protected static $_destroyRelations = ['productOptionValues'];

    public function option()
    {
        return $this->belongsTo(Option::class, 'option_id', 'id');
    }

    public function productOptionValues()
    {
        return $this->hasMany(
            ProductOptionValue::class,
            ['product_id', 'option_id'],
            ['product_id', 'option_id'],
        );
    }
}
