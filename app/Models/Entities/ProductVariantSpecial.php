<?php

namespace App\Models\Entities;

use App\Models\Base\Base;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductVariantSpecial extends Base
{
    use SoftDeletes;

    protected $table = 'product_variant_special';
    protected $primaryKeyAutoIncrement = 'id';
    public $timestamps = true;

    protected $casts = [
        'date_start'    => 'datetime',
        'date_end'      => 'datetime',
        'price'         => 'float',
        'priority'      => 'integer',
        'user_group_id' => 'integer',
    ];

    public function productVariant()
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id', 'id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }
}
