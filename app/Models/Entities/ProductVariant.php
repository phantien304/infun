<?php

namespace App\Models\Entities;

use App\Models\Base\Base;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductVariant extends Base
{
    use SoftDeletes;

    protected $table = 'product_variant';
    protected $primaryKeyAutoIncrement = 'id';
    public $timestamps = true;

    protected $casts = [
        'price'       => 'float',
        'weight'      => 'float',
        'is_default'  => 'boolean',
        'sort_order'  => 'integer',
        'points'      => 'integer',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }

    public function productVariantAttributes()
    {
        return $this->hasMany(ProductVariantAttribute::class, 'product_variant_id', 'id');
    }

    public function productStocks()
    {
        return $this->hasMany(ProductStock::class, 'product_variant_id', 'id');
    }

    public function productStock()
    {
        return $this->hasOne(ProductStock::class, 'product_variant_id', 'id')
            ->where('warehouse_id', ProductStock::DEFAULT_WAREHOUSE_ID);
    }

    public function descriptions()
    {
        return $this->hasMany(ProductVariantDescription::class, 'product_variant_id', 'id');
    }

    public function description()
    {
        return $this->hasOne(ProductVariantDescription::class, 'product_variant_id', 'id')->forLocale();
    }
}
