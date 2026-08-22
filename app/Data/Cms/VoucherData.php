<?php

namespace App\Data\Cms;

use App\Models\Entities\Voucher;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Data;

/**
 * DTO voucher (thẻ quà tặng / gift card) cho CMS.
 *
 * Khác mt219 — bảng `voucher` đã refactor (2026_06_13_000000):
 *  - `code` VARCHAR(10) → VARCHAR(20), UNIQUE.
 *  - thêm `status` (1 active / 2 expired / 3 fully_used / 4 revoked),
 *    `redeemed_balance` (denormalize SUM voucher_history), `date_expire`,
 *    `sent_at`.
 *  - `available_balance` = amount - redeemed_balance, tính ở model
 *    (Voucher::availableBalance) — CMS chỉ đọc.
 *
 * `redeemed_balance` / `sent_at` KHÔNG nhận từ CMS: một cái là sổ tiền đã
 * dùng (VoucherRepository::incrementRedeemed ghi), một cái do job gửi mail
 * đóng dấu.
 */
class VoucherData extends Data
{
    public function __construct(
        public int $id,
        public ?int $order_id,
        public string $code,
        public ?string $from_name,
        public ?string $from_email,
        public ?string $to_name,
        public ?string $to_email,
        public ?int $voucher_theme_id,
        public ?string $voucher_theme_name,
        public ?string $message,
        public float $amount,
        public float $redeemed_balance,
        public float $available_balance,
        public int $status,
        public ?string $date_expire,
        public ?string $sent_at,
        public ?string $created_at,
        public ?string $deleted_at,
        public Collection $voucher_histories,
    ) {
    }

    public static function fromModel(Voucher $voucher): self
    {
        $theme = $voucher->relationLoaded('voucherTheme') ? $voucher->voucherTheme : null;

        return new self(
            id: (int) $voucher->id,
            order_id: $voucher->order_id === null ? null : (int) $voucher->order_id,
            code: (string) $voucher->code,
            from_name: $voucher->from_name,
            from_email: $voucher->from_email,
            to_name: $voucher->to_name,
            to_email: $voucher->to_email,
            voucher_theme_id: $voucher->voucher_theme_id === null ? null : (int) $voucher->voucher_theme_id,
            voucher_theme_name: $theme?->relationLoaded('voucherThemeDescriptions')
                ? $theme->voucherThemeDescriptions->first()?->name
                : null,
            message: $voucher->message,
            amount: (float) $voucher->amount,
            redeemed_balance: (float) $voucher->redeemed_balance,
            available_balance: $voucher->availableBalance(),
            status: (int) $voucher->status,
            date_expire: $voucher->date_expire?->toDateString(),
            sent_at: $voucher->sent_at?->toDateTimeString(),
            created_at: $voucher->created_at?->toDateTimeString(),
            deleted_at: $voucher->deleted_at?->toDateTimeString(),
            voucher_histories: $voucher->relationLoaded('voucherHistories')
                ? VoucherHistoryItemData::collect($voucher->voucherHistories, Collection::class)
                : collect(),
        );
    }
}
