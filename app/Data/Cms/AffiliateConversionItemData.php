<?php

namespace App\Data\Cms;

use App\Models\Entities\AffiliateConversion;
use Spatie\LaravelData\Data;

/**
 * Một dòng hoa hồng (`affiliate_conversion`) ở màn đối soát CMS.
 *
 * Tên có hậu tố `Item` để KHÔNG lẫn với `App\Data\Affiliate\AffiliateConversionData`
 * — DTO đầu vào của luồng ghi nhận conversion lúc đặt hàng. Hai thứ khác nhau:
 * cái kia là lệnh ghi, cái này là bản đọc cho admin.
 *
 * `order_total` / `commission` / `commission_rate` đều là SNAPSHOT lúc đặt
 * hàng — sửa rate của KOL sau đó không hồi tố các đơn cũ, và đó là chủ ý.
 */
class AffiliateConversionItemData extends Data
{
    public function __construct(
        public int $id,
        public int $affiliate_id,
        public ?string $affiliate_code,
        public ?string $affiliate_name,
        public int $order_id,
        public ?string $order_code,
        public ?string $order_email,
        public ?int $click_id,
        public ?string $coupon_code,
        public int $order_total,
        public int $commission,
        public ?float $commission_rate,
        public int $status,
        public ?int $payout_id,
        public ?string $approved_at,
        public ?string $created_at,
    ) {
    }

    public static function fromModel(AffiliateConversion $conversion): self
    {
        $affiliate = $conversion->relationLoaded('affiliate') ? $conversion->affiliate : null;
        $order     = $conversion->relationLoaded('order') ? $conversion->order : null;

        return new self(
            id: (int) $conversion->id,
            affiliate_id: (int) $conversion->affiliate_id,
            affiliate_code: $affiliate?->code,
            affiliate_name: ($affiliate && $affiliate->relationLoaded('user'))
                ? $affiliate->user?->full_name
                : null,
            order_id: (int) $conversion->order_id,
            // Mã hoá đơn hiển thị = prefix + số; đơn cũ chưa phát hành hoá đơn
            // thì để NULL thay vì chuỗi rỗng, FE tự fallback về order_id.
            order_code: $order
                ? (trim((string) $order->invoice_prefix . (string) $order->invoice_no) ?: null)
                : null,
            order_email: $order?->email,
            click_id: $conversion->click_id === null ? null : (int) $conversion->click_id,
            coupon_code: $conversion->coupon_code,
            order_total: (int) $conversion->order_total,
            commission: (int) $conversion->commission,
            commission_rate: $conversion->commission_rate === null ? null : (float) $conversion->commission_rate,
            status: (int) $conversion->status,
            payout_id: $conversion->payout_id === null ? null : (int) $conversion->payout_id,
            approved_at: $conversion->approved_at?->toDateTimeString(),
            created_at: $conversion->created_at?->toDateTimeString(),
        );
    }
}
