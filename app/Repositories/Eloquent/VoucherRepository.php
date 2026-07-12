<?php

namespace App\Repositories\Eloquent;

use App\Models\Entities\Orders;
use App\Models\Entities\Voucher;
use App\Models\Entities\VoucherHistory;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Concerns\CacheableRepository;
use App\Repositories\Interfaces\VoucherRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class VoucherRepository extends QueryableRepository implements VoucherRepositoryInterface
{
    use CacheableRepository;

    public function model(): string
    {
        return Voucher::class;
    }

    public function findByCode(string $code): ?Voucher
    {
        $code = trim($code);
        if ($code === '') {
            return null;
        }
        return $this->resetModel()
            ->newQuery()
            ->where('code', $code)
            ->with(['voucherTheme'])
            ->first();
    }

    public function findByCodes(array $codes): Collection
    {
        $codes = array_values(array_filter(array_map('trim', $codes), fn ($c) => $c !== ''));
        if (empty($codes)) {
            return collect();
        }
        return $this->resetModel()
            ->newQuery()
            ->whereIn('code', $codes)
            ->with(['voucherTheme'])
            ->get()
            ->keyBy('code');
    }

    public function listForEmail(string $email): Collection
    {
        $email = trim(strtolower($email));
        if ($email === '') {
            return collect();
        }
        return $this->resetModel()
            ->newQuery()
            ->forEmail($email)
            ->with(['voucherTheme'])
            ->orderByDesc('id')
            ->get();
    }

    // === Write-side port từ VoucherService (history + balance/status) ===

    public function recordHistory(int $voucherId, int $orderId, ?int $userId, int $amount, int $status): void
    {
        VoucherHistory::create([
            'voucher_id' => $voucherId,
            'order_id'   => $orderId,
            'user_id'    => $userId,
            'amount'     => $amount,
            'status'     => $status,
        ]);
    }

    public function historyForOrderByStatus(int $orderId, int $status): Collection
    {
        return VoucherHistory::query()
            ->forOrder($orderId)
            ->where('status', $status)
            ->get(['id', 'voucher_id', 'amount']);
    }

    public function historyForOrderByStatuses(int $orderId, array $statuses): Collection
    {
        return VoucherHistory::query()
            ->forOrder($orderId)
            ->whereIn('status', $statuses)
            ->get(['id', 'voucher_id', 'amount', 'status']);
    }

    public function markHistoryStatus(array $ids, int $status): void
    {
        if (empty($ids)) {
            return;
        }
        VoucherHistory::query()
            ->whereIn('id', $ids)
            ->update(['status' => $status, 'updated_at' => now()]);
    }

    public function incrementRedeemed(int $voucherId, float $amount): void
    {
        DB::table('voucher')->where('id', $voucherId)->increment('redeemed_balance', $amount);
    }

    public function decrementRedeemed(int $voucherId, float $amount): void
    {
        DB::table('voucher')
            ->where('id', $voucherId)
            ->where('redeemed_balance', '>=', $amount)
            ->decrement('redeemed_balance', $amount);
    }

    public function markFullyUsed(array $voucherIds, int $activeStatus, int $fullyUsedStatus): void
    {
        if (empty($voucherIds)) {
            return;
        }
        DB::table('voucher')
            ->whereIn('id', $voucherIds)
            ->where('status', $activeStatus)
            ->whereColumn('redeemed_balance', '>=', 'amount')
            ->update(['status' => $fullyUsedStatus]);
    }

    public function reactivateVouchers(array $voucherIds, int $fullyUsedStatus, int $activeStatus): void
    {
        if (empty($voucherIds)) {
            return;
        }
        DB::table('voucher')
            ->whereIn('id', $voucherIds)
            ->where('status', $fullyUsedStatus)
            ->whereColumn('redeemed_balance', '<', 'amount')
            ->update(['status' => $activeStatus]);
    }

    public function flushCache(): void
    {
        $this->forgetCacheTagged([getCoreConfig('voucher.cache.tag_root')]);
    }

    // === Legacy API ===

    /**
     * Port logic CheckoutMarketing::getVoucher cũ. KHÔNG dùng cho flow Shopee
     * mới — service mới gọi `findByCode` + `VoucherService::validate`.
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
