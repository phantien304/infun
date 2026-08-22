<?php

namespace App\Data\Cms;

use App\Models\Entities\VoucherHistory;
use Spatie\LaravelData\Data;

/**
 * Dòng lịch sử redeem voucher — tab "Lịch sử" của voucher form.
 * status: 1=applied (đang ở giỏ), 2=confirmed (đơn đã thanh toán),
 * 3=refunded (đơn huỷ, trả lại số dư) — App\Enums\VoucherHistoryStatus.
 */
class VoucherHistoryItemData extends Data
{
    public function __construct(
        public int $id,
        public int $order_id,
        public ?int $user_id,
        public float $amount,
        public int $status,
        public ?string $created_at,
    ) {
    }

    public static function fromModel(VoucherHistory $history): self
    {
        return new self(
            id: (int) $history->id,
            order_id: (int) $history->order_id,
            user_id: $history->user_id === null ? null : (int) $history->user_id,
            amount: (float) $history->amount,
            status: (int) $history->status,
            created_at: $history->created_at?->toDateTimeString(),
        );
    }
}
