<?php

namespace App\Models\Entities;

use App\Models\Base\Base;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductOptionValue extends Base
{
    use SoftDeletes;

    protected $table = 'product_option_value';
    protected $primaryKeyAutoIncrement = 'id';
    public $timestamps = true;
    protected $fillable = ['product_option_id', 'option_value_id', 'image', 'sort_order', 'price', 'deleted_at'];

    public function optionValue()
    {
        return $this->belongsTo(OptionValue::class, 'option_value_id', 'id');
    }

    public function productOption()
    {
        return $this->belongsTo(ProductOption::class, 'product_option_id', 'id');
    }
}
