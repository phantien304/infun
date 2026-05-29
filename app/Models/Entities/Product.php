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
        'productSpecials',
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

    public function productSpecials()
    {
        return $this->hasMany(ProductSpecial::class, 'product_id', 'id');
    }

    public function productSpecial()
    {
        return $this->hasOne(ProductSpecial::class, 'product_id', 'id')->ofMany(
            ['priority' => 'max'],
            fn ($q) => $q->dateStartToEnd()
                ->where('user_group_id', getUserGroupId())
        );
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
        $now = Carbon::now();

        return $query->whereHas('productSpecials', function ($q) use ($now) {
            $q->where('user_group_id', getUserGroupId())
                ->where(fn ($qq) => $qq->whereNull('date_start')->orWhere('date_start', '<=', $now))
                ->where(fn ($qq) => $qq->whereNull('date_end')->orWhere('date_end', '>', $now));
        });
    }

    public function scopeEffectivePriceBetween(Builder $query, ?int $min, ?int $max): Builder
    {
        if ($min === null && $max === null) {
            return $query;
        }

        [$effective, $bindings] = self::effectivePriceExpression();

        if ($min !== null && $max !== null) {
            return $query->whereRaw("{$effective} BETWEEN ? AND ?", [...$bindings, $min, $max]);
        }
        if ($min !== null) {
            return $query->whereRaw("{$effective} >= ?", [...$bindings, $min]);
        }
        return $query->whereRaw("{$effective} <= ?", [...$bindings, $max]);
    }

    public function scopeOrderByEffectivePrice(Builder $query, string $dir = 'asc'): Builder
    {
        $dir = strtolower($dir) === 'desc' ? 'desc' : 'asc';

        [$effective, $bindings] = self::effectivePriceExpression();

        return $query->orderByRaw("{$effective} $dir", $bindings);
    }

    protected static function effectivePriceExpression(): array
    {
        $sql = '
            COALESCE(
                (
                    SELECT ps.price FROM product_special ps
                    WHERE ps.product_id    = product.id
                      AND ps.user_group_id = ?
                      AND (ps.date_start IS NULL OR ps.date_start <= ?)
                      AND (ps.date_end   IS NULL OR ps.date_end   >= ?)
                    ORDER BY ps.priority DESC
                    LIMIT 1
                ),
                product.price
            )
        ';

        $now = Carbon::now();

        return [$sql, [getUserGroupId(), $now, $now]];
    }
}
