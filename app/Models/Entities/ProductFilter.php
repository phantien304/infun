<?php

namespace App\Models\Entities;

use App\Models\Base\Base;

class ProductFilter extends Base
{
    protected $table = 'product_filter';
    public $primaryKey = ['product_id', 'filter_value_id'];
    public $incrementing = false;
    public $timestamps = false;
    protected $fillable = ['product_id', 'filter_id', 'filter_value_id'];

    public function filterValue()
    {
        return $this->belongsTo(FilterValue::class, 'filter_value_id', 'id');
    }
}
