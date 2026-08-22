<?php

namespace App\Data\Cms;

use App\Models\Entities\CouponHistory;
use Spatie\LaravelData\Data;

/**
 * Dòng lịch sử dùng mã trong tab "Lịch sử" của coupon form.
 *
 * mt219 gom user vào mảng thủ công ở CouponController::_processCouponHistories();
 * ở đây eager load quan hệ `user` rồi phẳng hoá tên/email — CMS chỉ cần đọc.
 */
class CouponHistoryItemData extends Data
{
    public function __construct(
        public int $id,
        public ?int $order_id,
        public ?int $user_id,
        public ?string $user_name,
        public ?string $user_email,
        public float $amount,
        public int $status,
        public ?string $created_at,
    ) {
    }

    public static function fromModel(CouponHistory $history): self
    {
        $user = $history->relationLoaded('user') ? $history->user : null;

        return new self(
            id: (int) $history->id,
            order_id: $history->order_id === null ? null : (int) $history->order_id,
            user_id: $history->user_id === null ? null : (int) $history->user_id,
            user_name: $user?->name ?? $user?->fullname ?? null,
            user_email: $user?->email,
            amount: (float) $history->amount,
            status: (int) ($history->status?->value ?? 0),
            created_at: $history->created_at?->toDateTimeString(),
        );
    }
}
