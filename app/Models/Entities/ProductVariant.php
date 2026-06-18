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

    /**
     * Signature CHÍNH TẮC của một tổ hợp biến thể: MD5 của danh sách
     * "option_id:option_value_id" sort theo option_id, nối bằng '|'.
     * Khớp cột `attribute_signature` + UNIQUE (product_id, attribute_signature).
     *
     * NGUỒN SỰ THẬT DUY NHẤT — mọi nơi GHI variant (admin/seeder) lẫn TRA cứu
     * (CartService::resolveVariantId) PHẢI gọi hàm này, KHÔNG tự viết lại công
     * thức. Lệch 1 ký tự = signature khác = tra không ra variant (add-to-cart fail).
     *
     * @param  array<int,int>  $optionToValue  map [option_id => option_value_id]
     */
    public static function buildAttributeSignature(array $optionToValue): string
    {
        ksort($optionToValue);
        $parts = [];
        foreach ($optionToValue as $optionId => $valueId) {
            $parts[] = (int) $optionId . ':' . (int) $valueId;
        }

        return md5(implode('|', $parts));
    }

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
            ->where('warehouse_id', getCoreConfig('stock.default_warehouse_id'));
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

    public function productVariantSpecial()
    {
        return $this->hasOne(ProductVariantSpecial::class, 'product_variant_id', 'id')->ofMany(
            ['priority' => 'max'],
            fn ($q) => $q->dateStartToEnd()
                ->where('user_group_id', getUserGroupId())
        );
    }
}
