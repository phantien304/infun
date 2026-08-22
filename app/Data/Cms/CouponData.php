<?php

namespace App\Data\Cms;

use App\Models\Entities\Coupon;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Data;

/**
 * DTO coupon cho CMS (infuncms).
 *
 * So với mt219 (`app/Model/Entities/Coupon.php` + form.vue cũ) đã bổ sung:
 *  - `type` số (1=percent, 2=fixed, 3=freeship) thay cho CHAR 'P'/'F'.
 *    `shipping` giữ lại nhưng DEPRECATED — freeship nay là type=3.
 *  - `description`, `discount_max`, `min_subtotal`, `apply_scope`,
 *    `user_group_id`, `used_count`, `is_active`, `sort_order`, `badge`.
 *  - `total` giữ để backward-compat, code mới đọc `min_subtotal`.
 *
 * `used_count` CHỈ HIỂN THỊ — cột denormalize do CouponRepository
 * increment/decrement khi đơn dùng mã; CMS sửa tay sẽ làm sai quota.
 */
class CouponData extends Data
{
    public function __construct(
        public int $id,
        public ?string $name,
        public ?string $description,
        public ?string $code,
        public int $type,
        public float $discount,
        public ?float $discount_max,
        public ?float $total,
        public ?float $min_subtotal,
        public int $apply_scope,
        public ?int $user_group_id,
        public int $logged,
        public int $shipping,
        public ?string $date_start,
        public ?string $date_end,
        public ?int $uses_total,
        public ?int $uses_customer,
        public int $used_count,
        public bool $is_active,
        public int $sort_order,
        public ?string $badge,
        public ?string $deleted_at,
        public Collection $coupon_products,
        public Collection $coupon_categories,
        public Collection $coupon_histories,
    ) {
    }

    public static function fromModel(Coupon $coupon): self
    {
        return new self(
            id: (int) $coupon->id,
            name: $coupon->name,
            description: $coupon->description,
            code: $coupon->code,
            type: (int) $coupon->type,
            discount: (float) $coupon->discount,
            discount_max: $coupon->discount_max === null ? null : (float) $coupon->discount_max,
            total: $coupon->total === null ? null : (float) $coupon->total,
            min_subtotal: $coupon->min_subtotal === null ? null : (float) $coupon->min_subtotal,
            apply_scope: (int) $coupon->apply_scope,
            user_group_id: $coupon->user_group_id === null ? null : (int) $coupon->user_group_id,
            logged: (int) $coupon->logged,
            shipping: (int) $coupon->shipping,
            date_start: $coupon->date_start?->toDateString(),
            date_end: $coupon->date_end?->toDateString(),
            uses_total: $coupon->uses_total === null ? null : (int) $coupon->uses_total,
            uses_customer: $coupon->uses_customer === null ? null : (int) $coupon->uses_customer,
            used_count: (int) $coupon->used_count,
            is_active: (bool) $coupon->is_active,
            sort_order: (int) $coupon->sort_order,
            badge: $coupon->badge,
            deleted_at: $coupon->deleted_at?->toDateTimeString(),
            coupon_products: $coupon->relationLoaded('products')
                ? $coupon->products->map(fn ($p) => [
                    'id'   => (int) $p->id,
                    'name' => $p->description?->name,
                ])->values()
                : collect(),
            coupon_categories: $coupon->relationLoaded('categories')
                ? $coupon->categories->map(fn ($c) => [
                    'id'    => (int) $c->id,
                    'title' => $c->description?->title,
                ])->values()
                : collect(),
            coupon_histories: $coupon->relationLoaded('couponHistories')
                ? CouponHistoryItemData::collect($coupon->couponHistories, Collection::class)
                : collect(),
        );
    }
}
