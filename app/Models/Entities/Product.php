<?php

namespace App\Models\Entities;

use App\Models\Base\Base;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;
use OwenIt\Auditing\Contracts\Auditable;

class Product extends Base implements Auditable
{
    use SoftDeletes;
    use Searchable;
    use \OwenIt\Auditing\Auditable;

    protected $table = 'product';
    protected $primaryKeyAutoIncrement = 'id';
    protected $fillable = [
        'model', 'sku', 'upc', 'ean', 'jan', 'isbn', 'mpn', 'location',
        'image', 'badge', 'date_available', 'link_sale', 'link_sale_custom',
        'manufacturer_id', 'tax_class_id', 'stock_status_id', 'shipping',
        'subtract', 'is_add_cart', 'is_custom', 'is_review',
        'length', 'width', 'height', 'length_class_id',
        'weight', 'weight_class_id', 'points', 'sort_order', 'has_variants',
        'deleted_at',
    ];
    protected $auditExclude = [
        'viewed', 'updated_at',
        'rating_avg', 'rating_sum', 'review_count', 'rating_distribution', 'rating_updated_at',
        'min_variant_price', 'max_variant_price', 'max_variant_discount_percent',
    ];
    public $timestamps = true;
    protected static array $destroyRelations = [
        'productAttributes',
        'productCategories',
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

    public function toSearchableArray(): array
    {
        $desc = $this->relationLoaded('description') ? $this->description : $this->description()->first();

        $this->loadMissing(['productCategories', 'productFilters']);

        return [
            'id'                          => (int) $this->id,
            'sku'                         => (string) ($this->sku ?? ''),
            'model'                       => (string) ($this->model ?? ''),
            'name'                        => (string) ($desc->name ?? ''),
            'description'                 => strip_tags((string) ($desc->description ?? '')),
            'manufacturer_id'             => (int) ($this->manufacturer_id ?? 0),
            'sort_order'                  => (int) ($this->sort_order ?? 0),
            'has_variants'                => (int) ($this->has_variants ?? 0),
            'min_variant_price'           => $this->min_variant_price !== null ? (float) $this->min_variant_price : null,
            'max_variant_price'           => $this->max_variant_price !== null ? (float) $this->max_variant_price : null,
            'max_variant_discount_percent' => $this->max_variant_discount_percent !== null ? (int) $this->max_variant_discount_percent : 0,
            'viewed'                      => (int) ($this->viewed ?? 0),
            'rating_avg'                  => $this->rating_avg !== null ? (float) $this->rating_avg : 0.0,
            'created_at'                  => $this->created_at?->timestamp,
            'category_id'                 => $this->productCategories->pluck('category_id')->map(fn ($v) => (int) $v)->values()->all(),
            'filter_value_id'             => $this->productFilters->pluck('filter_value_id')->map(fn ($v) => (int) $v)->values()->all(),
        ];
    }

    public function searchableAs(): string
    {
        return config('scout.prefix') . 'products_' . app()->getLocale();
    }

    protected function makeAllSearchableUsing(Builder $query): Builder
    {
        return $query->with(['description', 'productCategories', 'productFilters']);
    }

    public function searchableAllLocales(): void
    {
        $original = app()->getLocale();

        try {
            foreach (config('app.locales', [$original]) as $locale) {
                app()->setLocale($locale);
                $this->unsetRelation('description');
                $this->searchable();
            }
        } finally {
            app()->setLocale($original);
            $this->unsetRelation('description');
        }
    }

    public function unsearchableAllLocales(): void
    {
        $original = app()->getLocale();

        try {
            foreach (config('app.locales', [$original]) as $locale) {
                app()->setLocale($locale);
                $this->unsearchable();
            }
        } finally {
            app()->setLocale($original);
        }
    }

    public function productImages()
    {
        return $this->hasMany(ProductImage::class, 'product_id', 'id');
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

    public function getMinimumAttribute(): int
    {
        return (int) ($this->defaultVariant?->minimum ?? 1);
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
        return $this->hasOne(StockStatusDescription::class, 'stock_status_id', 'stock_status_id')->forLocale();
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

    // Dùng thẳng min_variant_price/max_variant_price — cột denormalized đã
    // được ProductVariantAggregateObserver tính đúng "giá hiệu lực" (ưu tiên
    // product_variant_special theo priority, fallback giá gốc), có index
    // composite (has_variants, min_variant_price). TRƯỚC ĐÂY 2 scope này tự
    // build lại đúng phép tính đó bằng subquery tương quan lồng 2 tầng CHO
    // MỖI DÒNG — với 500k sản phẩm là thảm hoạ (đo trực tiếp: ORDER BY treo
    // >60s, PHP-FPM timeout 15s → 502 hàng loạt, sự cố 2026-08-11).
    //
    // Đánh đổi duy nhất: 2 cột denormalized tính theo user group MẶC ĐỊNH
    // (ProductVariantAggregateObserver dùng getCoreConfig('user.default_group_id')),
    // không phải user group của người đang request. Chấp nhận được cho
    // sort/filter theo giá (khách vãng lai — đa số traffic — vẫn đúng
    // group mặc định); giá HIỂN THỊ trên từng thẻ sản phẩm vẫn tính đúng
    // riêng theo user group thật ở chỗ khác, không bị ảnh hưởng.
    public function scopeEffectivePriceBetween(Builder $query, ?int $min, ?int $max): Builder
    {
        if ($min === null && $max === null) {
            return $query;
        }
        if ($min !== null && $max !== null) {
            return $query->where('product.min_variant_price', '<=', $max)
                ->where('product.max_variant_price', '>=', $min);
        }
        if ($min !== null) {
            return $query->where('product.max_variant_price', '>=', $min);
        }
        return $query->where('product.min_variant_price', '<=', $max);
    }

    public function scopeOrderByEffectivePrice(Builder $query, string $dir = 'asc'): Builder
    {
        $dir = strtolower($dir) === 'desc' ? 'desc' : 'asc';
        $column = $dir === 'desc' ? 'max_variant_price' : 'min_variant_price';

        return $query->orderBy("product.{$column}", $dir);
    }
}
