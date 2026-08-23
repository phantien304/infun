<?php

namespace App\Data\Cms;

use App\Models\Entities\Affiliate;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Data;

/**
 * DTO hồ sơ KOL cho CMS — Phase 5 AFFILIATE-PLAN.md (mt219 KHÔNG có affiliate).
 *
 * `code` trả về nhưng KHÔNG nhận từ form: mã ref đã nằm trên link và bài đăng
 * của KOL, đổi là gãy toàn bộ attribution đang chạy.
 *
 * `commission_rate` NULL nghĩa là dùng rate global
 * (`config_affiliate_commission_rate`) — không phải 0%.
 */
class AffiliateData extends Data
{
    public function __construct(
        public int $id,
        public int $user_id,
        public ?string $user_name,
        public ?string $user_email,
        public string $code,
        public int $status,
        public ?float $commission_rate,
        public ?array $payment_info,
        public int $clicks_count,
        public ?string $approved_at,
        public ?string $created_at,
        public Collection $coupons,
        public Collection $links,
    ) {
    }

    public static function fromModel(Affiliate $affiliate): self
    {
        $user = $affiliate->relationLoaded('user') ? $affiliate->user : null;
        $info = $affiliate->payment_info;

        return new self(
            id: (int) $affiliate->id,
            user_id: (int) $affiliate->user_id,
            user_name: $user?->full_name,
            user_email: $user?->email,
            code: (string) $affiliate->code,
            status: (int) $affiliate->status,
            commission_rate: $affiliate->commission_rate === null ? null : (float) $affiliate->commission_rate,
            payment_info: is_array($info) ? $info : null,
            clicks_count: (int) $affiliate->clicks_count,
            approved_at: $affiliate->approved_at?->toDateTimeString(),
            created_at: $affiliate->created_at?->toDateTimeString(),
            coupons: $affiliate->relationLoaded('coupons')
                ? $affiliate->coupons->map(fn ($c) => [
                    'id'   => (int) $c->id,
                    'code' => $c->code,
                    'name' => $c->name,
                ])->values()
                : collect(),
            links: $affiliate->relationLoaded('affiliateLinks')
                ? AffiliateLinkItemData::collect($affiliate->affiliateLinks, Collection::class)
                : collect(),
        );
    }
}
