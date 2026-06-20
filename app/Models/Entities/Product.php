<?php

namespace App\Models\Entities;

use App\Models\Base\Base;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

class Product extends Base implements Auditable
{
    use SoftDeletes;
    use \OwenIt\Auditing\Auditable;

    protected $table = 'product';
    protected $primaryKeyAutoIncrement = 'id';
    protected $auditExclude = ['viewed', 'rating', 'total_rating', 'updated_at'];
    public $timestamps = true;
    protected static array $destroyRelations = [
        'productAttributes',
        'productCategories',
        'productDiscounts',
        'productDrafts',
        'productFilters',
        'productImages',
        'productIngredients',
        'productOptions',
        'productRelated',
        'productRewards',
        'userWishlists',
        'couponProducts'
    ];

    public function productImages()
    {
        return $this->hasMany(ProductImage::class, 'product_id', 'id');
    }

    public function productDiscounts()
    {
        return $this->hasMany(ProductDiscount::class, 'product_id', 'id');
    }

    public function description()
    {
        return $this->hasOne(ProductDescription::class, 'product_id', 'id')->forLocale();
    }

    public function descriptions()
    {
        return $this->hasMany(ProductDescription::class, 'product_id', 'id');
    }

    public function productRelated()
    {
        return $this->hasMany(ProductRelated::class, 'product_id', 'id');
    }

    public function productCategories()
    {
        return $this->hasMany(ProductCategory::class, 'product_id', 'id');
    }

    public function productAttributes()
    {
        return $this->hasMany(ProductAttribute::class, 'product_id', 'id');
    }

    public function productOptions()
    {
        return $this->hasMany(ProductOption::class, 'product_id', 'id');
    }

    public function productVariants()
    {
        return $this->hasMany(ProductVariant::class, 'product_id', 'id');
    }

    public function defaultVariant()
    {
        return $this->hasOne(ProductVariant::class, 'product_id', 'id')
            ->ofMany(['is_default' => 'max']);
    }

    public function getPriceAttribute(): float
    {
        return (float) ($this->defaultVariant?->price ?? 0);
    }

    public function productFilters()
    {
        return $this->hasMany(ProductFilter::class, 'product_id', 'id');
    }

    public function productIngredients()
    {
        return $this->hasMany(ProductIngredient::class, 'product_id', 'id');
    }

    public function manufacturer()
    {
        return $this->belongsTo(Manufacturer::class, 'manufacturer_id', 'id');
    }

    public function stockStatus()
    {
        return $this->hasOne(StockStatus::class, 'id', 'stock_status_id')->forLocale();
    }

    public function weightClass()
    {
        return $this->belongsTo(WeightClass::class, 'weight_class_id', 'id');
    }

    public function productDraft()
    {
        return $this->belongsTo(ProductDraft::class, 'id', 'product_id');
    }

    public function productDrafts()
    {
        return $this->hasMany(ProductDraft::class, 'product_id', 'id');
    }

    public function productRewards()
    {
        return $this->hasMany(ProductReward::class, 'product_id', 'id');
    }

    public function userWishlist()
    {
        return $this->belongsTo(UserWishlist::class, 'id', 'product_id');
    }

    public function userWishlists()
    {
        return $this->hasMany(UserWishlist::class, 'product_id', 'id');
    }

    public function couponProducts()
    {
        return $this->hasMany(CouponProduct::class, 'product_id', 'id');
    }

    public function scopeHasActiveSpecial(Builder $query): Builder
    {
        return $query->whereHas('productVariants.productVariantSpecials', function ($q) {
            $q->where('user_group_id', getUserGroupId())->dateStartToEnd();
        });
    }

    public function scopeEffectivePriceBetween(Builder $query, ?int $min, ?int $max): Builder
    {
        if ($min === null && $max === null) {
            return $query;
        }

        [$lowSql, $lowBindings]   = self::effectivePriceExpression('low');
        [$highSql, $highBindings] = self::effectivePriceExpression('high');

        if ($min !== null && $max !== null) {
            return $query->whereRaw(
                "({$lowSql}) <= ? AND ({$highSql}) >= ?",
                [...$lowBindings, $max, ...$highBindings, $min]
            );
        }
        if ($min !== null) {
            return $query->whereRaw("({$highSql}) >= ?", [...$highBindings, $min]);
        }
        return $query->whereRaw("({$lowSql}) <= ?", [...$lowBindings, $max]);
    }

    public function scopeOrderByEffectivePrice(Builder $query, string $dir = 'asc'): Builder
    {
        $dir = strtolower($dir) === 'desc' ? 'desc' : 'asc';

        [$sql, $bindings] = self::effectivePriceExpression($dir === 'desc' ? 'high' : 'low');

        return $query->orderByRaw("{$sql} $dir", $bindings);
    }

    protected static function effectivePriceExpression(string $which = 'low'): array
    {
        $agg = $which === 'high' ? 'MAX' : 'MIN';

        $variantSpecialSql = '(
            SELECT pvs.price FROM product_variant_special pvs
            WHERE pvs.product_variant_id = pv.id
              AND pvs.user_group_id      = ?
              AND (pvs.date_start IS NULL OR pvs.date_start <= ?)
              AND (pvs.date_end   IS NULL OR pvs.date_end   >  ?)
              AND pvs.deleted_at IS NULL
            ORDER BY pvs.priority DESC
            LIMIT 1
        )';

        $variantAggSql = "(
            SELECT {$agg}(COALESCE({$variantSpecialSql}, pv.price))
            FROM product_variant pv
            WHERE pv.product_id = product.id
              AND pv.deleted_at IS NULL
        )";

        $defaultVariantSpecialSql = '(
            SELECT pvs.price FROM product_variant_special pvs
            JOIN product_variant dpv ON dpv.id = pvs.product_variant_id
            WHERE dpv.product_id    = product.id
              AND dpv.is_default    = 1
              AND dpv.deleted_at IS NULL
              AND pvs.user_group_id = ?
              AND (pvs.date_start IS NULL OR pvs.date_start <= ?)
              AND (pvs.date_end   IS NULL OR pvs.date_end   >  ?)
              AND pvs.deleted_at IS NULL
            ORDER BY pvs.priority DESC
            LIMIT 1
        )';

        $defaultVariantPriceSql = '(
            SELECT pv.price FROM product_variant pv
            WHERE pv.product_id = product.id
              AND pv.is_default = 1
              AND pv.deleted_at IS NULL
            LIMIT 1
        )';

        $simpleSql = "COALESCE({$defaultVariantSpecialSql}, {$defaultVariantPriceSql})";

        $sql = "CASE
            WHEN product.has_variants = 1 AND product.min_variant_price IS NOT NULL
                THEN {$variantAggSql}
            ELSE {$simpleSql}
        END";

        $now = Carbon::now();

        return [$sql, [getUserGroupId(), $now, $now, getUserGroupId(), $now, $now]];
    }
}
