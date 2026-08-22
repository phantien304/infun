<?php

namespace App\Data\Cms;

use App\Models\Entities\VoucherRewardRule;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Data;

/**
 * DTO quy tắc tặng voucher theo giá trị đơn ("đơn từ 1tr tặng voucher 50k")
 * — TÍNH NĂNG MỚI, mt219 KHÔNG có.
 *
 * status: 1=active, 2=paused (App\Enums\VoucherRewardRuleStatus). `status`
 * trumps date window — admin pause giữa chừng bất kể date_end.
 *
 * `granted_count` CHỈ HIỂN THỊ — conditional UPDATE trong
 * VoucherRewardRuleRepository::incrementRewardRuleCount() giữ cho không
 * vượt `quota_total` khi 2 đơn hoàn tất cùng lúc; sửa tay ở CMS phá chống đua.
 * `quota_remaining` = quota_total - granted_count (NULL khi không giới hạn).
 */
class VoucherRewardRuleData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $description,
        public float $min_order_total,
        public float $reward_amount,
        public int $reward_expire_days,
        public ?int $max_per_user,
        public ?int $quota_total,
        public int $granted_count,
        public ?int $quota_remaining,
        public ?string $date_start,
        public ?string $date_end,
        public int $status,
        public int $sort_order,
        public ?string $deleted_at,
        public Collection $grants,
    ) {
    }

    public static function fromModel(VoucherRewardRule $rule): self
    {
        return new self(
            id: (int) $rule->id,
            name: (string) $rule->name,
            description: $rule->description,
            min_order_total: (float) $rule->min_order_total,
            reward_amount: (float) $rule->reward_amount,
            reward_expire_days: (int) $rule->reward_expire_days,
            max_per_user: $rule->max_per_user === null ? null : (int) $rule->max_per_user,
            quota_total: $rule->quota_total === null ? null : (int) $rule->quota_total,
            granted_count: (int) $rule->granted_count,
            quota_remaining: $rule->quotaRemaining(),
            date_start: $rule->date_start?->toDateTimeString(),
            date_end: $rule->date_end?->toDateTimeString(),
            status: (int) $rule->status,
            sort_order: (int) $rule->sort_order,
            deleted_at: $rule->deleted_at?->toDateTimeString(),
            grants: $rule->relationLoaded('grants')
                ? VoucherRewardGrantItemData::collect($rule->grants, Collection::class)
                : collect(),
        );
    }
}
