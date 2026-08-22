<?php

namespace App\Data\Cms;

use App\Models\Entities\VoucherRewardGrant;
use Spatie\LaravelData\Data;

/**
 * Biên bản PHÁT thưởng (`voucher_reward_grant`) — chỉ đọc ở CMS.
 * KHÔNG phải log sử dụng (sử dụng = `voucher_history`).
 */
class VoucherRewardGrantItemData extends Data
{
    public function __construct(
        public int $id,
        public int $order_id,
        public ?int $user_id,
        public string $email,
        public int $voucher_id,
        public ?string $voucher_code,
        public ?string $created_at,
    ) {
    }

    public static function fromModel(VoucherRewardGrant $grant): self
    {
        return new self(
            id: (int) $grant->id,
            order_id: (int) $grant->order_id,
            user_id: $grant->user_id === null ? null : (int) $grant->user_id,
            email: (string) $grant->email,
            voucher_id: (int) $grant->voucher_id,
            voucher_code: $grant->relationLoaded('voucher') ? $grant->voucher?->code : null,
            created_at: $grant->created_at?->toDateTimeString(),
        );
    }
}
