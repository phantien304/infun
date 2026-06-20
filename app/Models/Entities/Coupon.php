<?php

namespace App\Models\Entities;

use App\Models\Base\Base;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class Coupon extends Base
{
    use SoftDeletes;

    protected $table = 'coupon';
    protected $primaryKeyAutoIncrement = 'id';
    public $incrementing = true;
    public $timestamps = true;
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

    public function scopeSavedBy(Builder $query, int $userId): Builder
    {
        return $query->whereHas(
            'savedByUsers',
            fn ($q) => $q->where('user.id', $userId),
        );
    }
}
