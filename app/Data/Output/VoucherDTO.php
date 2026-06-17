<?php

namespace App\Data\Output;

use App\Models\Entities\Voucher;
use Illuminate\Support\Carbon;
use Spatie\LaravelData\Data;

/**
 * Voucher DTO (gift card cá nhân) cho modal "Voucher của tôi".
 *
 * Pre-compute label hiển thị (amountLabel, redeemedLabel, availableLabel,
 * dateExpireLabel, statusLabel) trong DTO — blade chỉ render.
 *
 * `redeemable` = active + chưa expire + còn balance. Service set khi factory.
 */
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
    ) {}

    public static function fromModel(
        Voucher $voucher,
        bool $redeemable = true,
        ?string $notRedeemableReason = null,
    ): self {
        $currency = (string) getConfigDb('config_currency');
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
            themeName:           null, // resolve qua theme description nếu cần i18n
            amount:              $amount,
            redeemedBalance:     $redeemed,
            availableBalance:    $available,
            amountLabel:         number_format($amount) . $currency,
            redeemedLabel:       number_format($redeemed) . $currency,
            availableLabel:      number_format($available) . $currency,
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
        return match ($status) {
            (int) getCoreConfig('voucher.status.active')     => 'Còn hiệu lực',
            (int) getCoreConfig('voucher.status.expired')    => 'Đã hết hạn',
            (int) getCoreConfig('voucher.status.fully_used') => 'Đã dùng hết',
            (int) getCoreConfig('voucher.status.revoked')    => 'Đã thu hồi',
            default                                            => 'Không xác định',
        };
    }

    private static function resolveExpireLabel(Voucher $voucher): string
    {
        if (! $voucher->date_expire) {
            return 'Không thời hạn';
        }
        $end = Carbon::parse($voucher->date_expire);
        $now = Carbon::now();
        if ($end->lt($now)) {
            return 'Đã hết hạn ' . $end->format('d/m/Y');
        }
        $diffDays = $now->diffInDays($end, false);
        if ($diffDays <= 7) {
            return 'Còn ' . max(1, (int) $diffDays) . ' ngày';
        }
        return 'HSD: ' . $end->format('d/m/Y');
    }
}
