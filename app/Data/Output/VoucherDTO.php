<?php

namespace App\Data\Output;

use App\Enums\VoucherStatus;
use App\Models\Entities\Voucher;
use Illuminate\Support\Carbon;
use Spatie\LaravelData\Data;

class VoucherDTO extends Data
{
    public function __construct(
        public int $id,
        public string $code,
        public ?string $fromName,
        public ?string $fromEmail,
        public ?string $toName,
        public ?string $toEmail,
        public ?string $message,
        public ?string $themeImage,
        public ?string $themeName,
        public float $amount,
        public float $redeemedBalance,
        public float $availableBalance,
        public string $amountLabel,
        public string $redeemedLabel,
        public string $availableLabel,
        public int $status,
        public string $statusLabel,
        public ?string $dateExpire,
        public string $dateExpireLabel,
        public bool $redeemable,
        public ?string $notRedeemableReason,
    ) {
    }

    public static function fromModel(
        Voucher $voucher,
        bool $redeemable = true,
        ?string $notRedeemableReason = null,
    ): self {
        $amount = (float) $voucher->amount;
        $redeemed = (float) $voucher->redeemed_balance;
        $available = max(0.0, $amount - $redeemed);

        return new self(
            id:                  (int) $voucher->id,
            code:                (string) $voucher->code,
            fromName:            $voucher->from_name,
            fromEmail:           $voucher->from_email,
            toName:              $voucher->to_name,
            toEmail:             $voucher->to_email,
            message:             $voucher->message,
            themeImage:          $voucher->voucherTheme?->image,
            themeName:           null,
            amount:              $amount,
            redeemedBalance:     $redeemed,
            availableBalance:    $available,
            amountLabel:         money($amount),
            redeemedLabel:       money($redeemed),
            availableLabel:      money($available),
            status:              (int) $voucher->status,
            statusLabel:         self::resolveStatusLabel((int) $voucher->status),
            dateExpire:          $voucher->date_expire?->format('Y-m-d'),
            dateExpireLabel:     self::resolveExpireLabel($voucher),
            redeemable:          $redeemable,
            notRedeemableReason: $notRedeemableReason,
        );
    }

    private static function resolveStatusLabel(int $status): string
    {
        return VoucherStatus::fromInput($status)?->label()
            ?? trans('messages.checkout.voucher.status_unknown');
    }

    private static function resolveExpireLabel(Voucher $voucher): string
    {
        if (! $voucher->date_expire) {
            return trans('messages.checkout.voucher.expiry_none');
        }
        $end = Carbon::parse($voucher->date_expire);
        $now = Carbon::now();
        if ($end->lt($now)) {
            return sprintf(trans('messages.checkout.voucher.expiry_expired_at'), $end->format('d/m/Y'));
        }
        $diffDays = $now->diffInDays($end, false);
        if ($diffDays <= 7) {
            return sprintf(trans('messages.checkout.voucher.expiry_days'), max(1, (int) $diffDays));
        }
        return sprintf(trans('messages.checkout.voucher.expiry_date'), $end->format('d/m/Y'));
    }
}
