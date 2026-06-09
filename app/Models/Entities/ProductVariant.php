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
        'price'         => 'float',
        'regular_price' => 'float',
        'weight'        => 'float',
        'is_default'    => 'boolean',
        'sort_order'    => 'integer',
        'points'        => 'integer',
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

    public function productVariantSpecials()
    {
        return $this->hasMany(ProductVariantSpecial::class, 'product_variant_id', 'id');
    }

    /**
     * Special đại diện cho variant — priority cao nhất trong số đang active
     * (dateStartToEnd + đúng user_group_id). Mirror Product::productSpecial.
     *
     * Tên relation phản ánh tên bảng `product_variant_special` theo convention
     * dự án (CLAUDE.md "tên relation thể hiện tên bảng, ngoại trừ description").
     *
     * Eager-load cùng productVariants ở detailRelations để DTO + JS đọc
     * `effective_price = COALESCE(productVariantSpecial.price, variant.price)`
     * mà không N+1. Trong filter/sort list thì dùng correlated subquery của
     * Product::effectivePriceExpression — không eager-load relation này.
     */
    public function productVariantSpecial()
    {
        return $this->hasOne(ProductVariantSpecial::class, 'product_variant_id', 'id')->ofMany(
            ['priority' => 'max'],
            fn ($q) => $q->dateStartToEnd()
                ->where('user_group_id', getUserGroupId())
        );
    }
}
