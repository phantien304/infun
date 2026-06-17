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

    public function productVariants()
    {
        return $this->hasMany(ProductVariant::class, 'product_id', 'id');
    }

    /**
     * Resolve the single "default" variant — always present after the
     * unify_simple_product_stock migration: variant products mark their
     * primary tuple is_default=1, simple products own a hidden no-attribute
     * default variant. Cart / checkout / availability code uses this to
     * read product_stock through one consistent relation chain.
     */
    public function defaultVariant()
    {
        return $this->hasOne(ProductVariant::class, 'product_id', 'id')
            ->ofMany(['is_default' => 'max']);
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
        return $query->whereHas('productSpecials', function ($q) {
            $q->where('user_group_id', getUserGroupId())->dateStartToEnd();
        });
    }

    /**
     * Filter theo giá hiệu lực — range overlap.
     *
     * Variant product: khoảng giá là [min_variant_price, max_variant_price].
     * Simple product: điểm giá COALESCE(special.price, product.price).
     *
     * Một product khớp filter [filter_min, filter_max] khi khoảng giá của nó
     * GIAO với khoảng filter: LOW <= filter_max AND HIGH >= filter_min.
     * Áo có variant 50k–500k vẫn match filter "200k–300k" vì user mua được
     * variant trong khoảng đó. Anchor-on-min loại nhầm sản phẩm này.
     */
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

    /**
     * Sort theo giá hiệu lực.
     *
     * ASC dùng LOW (min_variant_price cho variant, COALESCE cho simple) — đặt
     * sản phẩm "giá khởi điểm rẻ nhất" lên đầu.
     * DESC dùng HIGH (max_variant_price cho variant) — đặt sản phẩm "đỉnh giá
     * cao nhất" lên đầu. Cùng anchor cho cả 2 phía gây bias variant về 1 đầu.
     */
    public function scopeOrderByEffectivePrice(Builder $query, string $dir = 'asc'): Builder
    {
        $dir = strtolower($dir) === 'desc' ? 'desc' : 'asc';

        [$sql, $bindings] = self::effectivePriceExpression($dir === 'desc' ? 'high' : 'low');

        return $query->orderByRaw("{$sql} $dir", $bindings);
    }

    /**
     * SQL fragment cho giá hiệu lực low/high.
     *
     * Variant product (has_variants=1 + min_variant_price IS NOT NULL):
     *   Aggregate MIN/MAX của effective variant price ngay trong subquery,
     *   không dựa min/max_variant_price denormalize. Effective per-variant =
     *   COALESCE(active product_variant_special.price, product_variant.price).
     *   product_special KHÔNG còn áp cho nhánh variant — quyết định Hướng B
     *   (xem CLAUDE.md "Cluster variant special").
     *
     *   Lý do dùng aggregate tại chỗ thay vì denormalize: variant_special là
     *   time-bound + per-user-group, không thể precompute aggregate cho mọi
     *   tổ hợp. Index `idx_pvs_lookup` cover query bên trong; 20 rows/page →
     *   chi phí chấp nhận được.
     *
     * Simple product hoặc variant product có data drift (has_variants=1 nhưng
     * min_variant_price NULL):
     *   COALESCE(active product_special.price, product.price). Giữ pattern cũ.
     *
     * Bindings: 6 placeholders theo thứ tự — [groupId, now, now] cho
     * variant_special subquery, rồi [groupId, now, now] cho product_special.
     * MySQL bind tất cả `?` trước khi CASE evaluate, KHÔNG short-circuit
     * binding theo nhánh.
     *
     * Toán tử so sánh ngày: nhánh variant_special follow HasAdvancedScopes
     * ('<=' start, '>' end strict — same scope ProductVariant::variantSpecial
     * dùng để load relation). Nhánh product_special giữ '<=' / '>=' theo
     * implementation cũ — đã có drift documented ở CLAUDE.md, sửa kèm task
     * unify riêng để khỏi scope creep.
     */
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

        $productSpecialSql = '(
            SELECT ps.price FROM product_special ps
            WHERE ps.product_id    = product.id
              AND ps.user_group_id = ?
              AND (ps.date_start IS NULL OR ps.date_start <= ?)
              AND (ps.date_end   IS NULL OR ps.date_end   >= ?)
            ORDER BY ps.priority DESC
            LIMIT 1
        )';

        $simpleSql = "COALESCE({$productSpecialSql}, product.price)";

        $sql = "CASE
            WHEN product.has_variants = 1 AND product.min_variant_price IS NOT NULL
                THEN {$variantAggSql}
            ELSE {$simpleSql}
        END";

        $now = Carbon::now();

        return [$sql, [getUserGroupId(), $now, $now, getUserGroupId(), $now, $now]];
    }
}
