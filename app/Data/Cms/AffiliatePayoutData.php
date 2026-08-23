<?php

namespace App\Data\Cms;

use App\Models\Entities\AffiliatePayout;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Data;

/**
 * Kỳ chi trả hoa hồng (`affiliate_payout`) — một dòng = (KOL, tháng).
 *
 * `amount` KHÔNG nhận từ form: nó là tổng hoa hồng thực tế đã gắn vào kỳ,
 * do AffiliatePayoutService đọc lại từ `affiliate_conversion` sau khi chốt.
 * Cho admin gõ tay số tiền là mở đường cho sổ sách lệch với chứng từ.
 */
class AffiliatePayoutData extends Data
{
    public function __construct(
        public int $id,
        public int $affiliate_id,
        public ?string $affiliate_code,
        public ?string $affiliate_name,
        public ?array $payment_info,
        public string $period,
        public int $amount,
        public int $status,
        public ?string $paid_at,
        public ?string $note,
        public ?string $created_at,
        public Collection $conversions,
    ) {
    }

    public static function fromModel(AffiliatePayout $payout): self
    {
        $affiliate = $payout->relationLoaded('affiliate') ? $payout->affiliate : null;
        $info = $affiliate?->payment_info;

        return new self(
            id: (int) $payout->id,
            affiliate_id: (int) $payout->affiliate_id,
            affiliate_code: $affiliate?->code,
            affiliate_name: ($affiliate && $affiliate->relationLoaded('user'))
                ? $affiliate->user?->full_name
                : null,
            payment_info: is_array($info) ? $info : null,
            period: (string) $payout->period,
            amount: (int) $payout->amount,
            status: (int) $payout->status,
            paid_at: $payout->paid_at?->toDateTimeString(),
            note: $payout->note,
            created_at: $payout->created_at?->toDateTimeString(),
            conversions: $payout->relationLoaded('affiliateConversions')
                ? AffiliateConversionItemData::collect($payout->affiliateConversions, Collection::class)
                : collect(),
        );
    }
}
