<?php

namespace App\Models\Entities;

use App\Models\Base\Base;

class ProductVariantAttribute extends Base
{
    protected $table = 'product_variant_attribute';
    protected $primaryKey = ['product_variant_id', 'option_id'];
    public $incrementing = false;
    public $timestamps = false;

    public function productVariant()
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id', 'id');
    }

    public function option()
    {
        return $this->belongsTo(Option::class, 'option_id', 'id');
    }

    public function optionValue()
    {
        return $this->belongsTo(OptionValue::class, 'option_value_id', 'id');
    }
}
