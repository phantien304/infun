<?php

namespace App\Repositories\Eloquent;

use App\Models\Entities\Orders;
use App\Models\Entities\Voucher;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Interfaces\VoucherRepositoryInterface;
use Illuminate\Support\Facades\DB;

class VoucherRepository extends QueryableRepository implements VoucherRepositoryInterface
{
    public function model(): string
    {
        return Voucher::class;
    }

    /**
     * Port logic CheckoutMarketing::getVoucher sang repo. Giữ nguyên semantics.
     *
     *  - Voucher gắn order: order phải đã complete VÀ có OrdersVoucher tương
     *    ứng (chống abuse — không cho dùng voucher trước khi order kích hoạt nó).
     *  - Số dư = voucher.amount + SUM(voucher_history.amount) — voucher_history
     *    ghi negative khi tiêu, nên cộng trở lại sẽ ra số dư còn lại. Logic này
     *    legacy, giữ nguyên.
     *  - amount <= 0 → invalid (hết).
     */
    public function resolveVoucher(?string $code): array
    {
        if (! filled($code)) {
            return [];
        }

        $voucher = $this->resetModel()
            ->where('code', $code)
            ->with(['voucherTheme.voucherThemeDescription' => fn ($q) => $q->where('language_code', app()->getLocale())])
            ->first();

        if (! $voucher) {
            return [];
        }

        // Voucher gắn order — check trạng thái + record OrdersVoucher
        if ($voucher->order_id) {
            $completeStatuses = (array) getConfigDb('order_complete_status_all', []);

            $orderOk = Orders::where('id', $voucher->order_id)
                ->whereIn('order_status_id', array_map('intval', $completeStatuses))
                ->exists();
            if (! $orderOk) {
                return [];
            }

            $linkOk = DB::table('orders_voucher')
                ->where('order_id', $voucher->order_id)
                ->where('voucher_id', $voucher->id)
                ->exists();
            if (! $linkOk) {
                return [];
            }
        }

        $usedDelta = (int) DB::table('voucher_history')
            ->where('voucher_id', $voucher->id)
            ->sum('amount');
        $remaining = (int) $voucher->amount + $usedDelta;

        if ($remaining <= 0) {
            return [];
        }

        return [
            'id'               => $voucher->id,
            'code'             => $voucher->code,
            'from_name'        => $voucher->from_name,
            'from_email'       => $voucher->from_email,
            'to_name'          => $voucher->to_name,
            'to_email'         => $voucher->to_email,
            'voucher_theme_id' => $voucher->voucher_theme_id,
            'theme'            => $voucher->voucherTheme?->voucherThemeDescription?->name,
            'message'          => $voucher->message,
            'image'            => $voucher->voucherTheme?->image,
            'amount'           => $remaining,
            'created_at'       => $voucher->created_at,
        ];
    }
}
