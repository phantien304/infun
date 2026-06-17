<?php

namespace App\Models\Entities;

use App\Models\Base\Base;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Master voucher Shopee-style.
 *
 * Type:
 *   1 = percent  → discount_value là %, kèm discount_max cap VND
 *   2 = fixed    → discount_value là VND tuyệt đối
 *   3 = freeship → bỏ qua discount_value, set shipping fee = 0
 *
 * Apply scope:
 *   0 = all        → áp mọi SP
 *   1 = products   → chỉ SP có row coupon_product
 *   2 = categories → chỉ SP thuộc category có row coupon_category
 *
 * KHÔNG hardcode literal — đọc qua `getCoreConfig('coupon.type.percent')` v.v.
 */
class Coupon extends Base
{
    use SoftDeletes;

    protected $table = 'coupon';
    protected $primaryKeyAutoIncrement = 'id';
    public $incrementing = true;
    public $timestamps = true;

    /**
     * `$guarded = []` bypass HasSchemaCache fillable forever-cache — schema
     * cluster đang refactor sẽ tiếp tục thêm cột mới, không muốn lệ thuộc
     * cache rebuild. Tất cả input vào CouponService phải đã sanitize qua
     * FormRequest / DTO trước khi tới đây.
     */
    protected $guarded = [];

    protected static array $destroyRelations = ['couponProducts', 'couponCategories'];

    protected $casts = [
        'discount'      => 'float',
        'discount_max'  => 'float',
        'total'         => 'float',
        'min_subtotal'  => 'float',
        'logged'        => 'integer',
        'shipping'      => 'integer',
        'type'          => 'integer',
        'apply_scope'   => 'integer',
        'user_group_id' => 'integer',
        'uses_total'    => 'integer',
        'uses_customer' => 'integer',
        'used_count'    => 'integer',
        'is_active'     => 'integer',
        'sort_order'    => 'integer',
        'date_start'    => 'datetime',
        'date_end'      => 'datetime',
    ];

    // === Relations ===

    public function couponProducts(): HasMany
    {
        return $this->hasMany(CouponProduct::class, 'coupon_id', 'id');
    }

    public function couponCategories(): HasMany
    {
        return $this->hasMany(CouponCategory::class, 'coupon_id', 'id');
    }

    public function couponHistories(): HasMany
    {
        return $this->hasMany(CouponHistory::class, 'coupon_id', 'id');
    }

    public function savedByUsers(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'user_coupon',
            'coupon_id',
            'user_id',
        )->withPivot('saved_at');
    }

    // === Scopes ===

    /**
     * Active = is_active=1 + trong window date_start/date_end + chưa hết quota
     * global. KHÔNG check apply_scope ở đây (cần cart context — đặt ở
     * CouponService::validateForCart).
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query
            ->where("{$this->getTable()}.is_active", 1)
            ->dateStartToEnd()
            ->where(function (Builder $q) {
                $q->whereNull("{$this->getTable()}.uses_total")
                    ->orWhereColumn(
                        "{$this->getTable()}.used_count",
                        '<',
                        "{$this->getTable()}.uses_total",
                    );
            });
    }

    /**
     * Voucher publicly available (user_group_id NULL) HOẶC dành riêng nhóm
     * khách của user hiện tại.
     */
    public function scopeForUserGroupOrPublic(Builder $query, ?int $userGroupId): Builder
    {
        if ($userGroupId === null) {
            return $query->whereNull("{$this->getTable()}.user_group_id");
        }
        return $query->where(function (Builder $q) use ($userGroupId) {
            $q->whereNull("{$this->getTable()}.user_group_id")
                ->orWhere("{$this->getTable()}.user_group_id", $userGroupId);
        });
    }

    public function scopeNotExpiredYet(Builder $query): Builder
    {
        $now = Carbon::now();
        return $query->where(fn (Builder $q) => $q
            ->whereNull("{$this->getTable()}.date_end")
            ->orWhere("{$this->getTable()}.date_end", '>=', $now));
    }

    /**
     * Filter voucher mà user đã lưu (qua bảng user_coupon).
     */
    public function scopeSavedBy(Builder $query, int $userId): Builder
    {
        return $query->whereHas(
            'savedByUsers',
            fn ($q) => $q->where('user.id', $userId),
        );
    }
}
