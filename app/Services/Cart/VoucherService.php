<?php

namespace App\Services\Cart;

use App\Data\Output\VoucherDTO;
use App\Models\Entities\Voucher;
use App\Models\Entities\VoucherHistory;
use App\Repositories\Interfaces\VoucherRepositoryInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class VoucherService
{
    public function __construct(
        protected VoucherRepositoryInterface $voucherRepo,
    ) {
    }

    public function applyCode(string $code, int $orderTotal): array
    {
        $code = trim($code);
        if ($code === '') {
            return ['ok' => false, 'message' => trans('messages.voucher.not_empty')];
        }

        $voucher = $this->voucherRepo->findByCode($code);
        if (! $voucher) {
            return ['ok' => false, 'message' => trans('messages.voucher.not_found', ['code' => $code])];
        }

        $reason = $this->validate($voucher, $orderTotal);
        if ($reason !== null) {
            return ['ok' => false, 'message' => trans('messages.voucher.error_reason', ['code' => $code, 'reason' => $reason])];
        }

        $applied = $this->getAppliedCodes();
        if (in_array($voucher->code, $applied, true)) {
            return ['ok' => false, 'message' => trans('messages.voucher.error_used', ['code' => $code])];
        }

        $applied[] = $voucher->code;
        session()->put(getCoreConfig('session.applied_vouchers'), $applied);

        return ['ok' => true, 'message' => trans('messages.voucher.saved')];
    }

    public function validate(Voucher $voucher, int $orderTotal): ?string
    {
        $statusActive = (int) getCoreConfig('voucher.status.active');
        if ((int) $voucher->status !== $statusActive) {
            return trans('messages.voucher.inactive');
        }

        if ($voucher->date_expire && Carbon::parse($voucher->date_expire)->lt(Carbon::now()->startOfDay())) {
            return trans('messages.voucher.expired');
        }

        $available = $voucher->availableBalance();
        if ($available <= 0) {
            return trans('messages.voucher.used_up');
        }

        if ($orderTotal <= 0) {
            return trans('messages.voucher.invalid_order');
        }

        return null;
    }

    public function resolveApplied(int $orderTotal): array
    {
        $codes = $this->getAppliedCodes();
        $applied = [];
        $errors = [];
        $residual = $orderTotal;

        $vouchers = $this->voucherRepo->findByCodes($codes);

        foreach ($codes as $code) {
            $voucher = $vouchers->get($code);
            if (! $voucher) {
                $errors[] = trans('messages.voucher.not_found', ['code' => $code]);
                continue;
            }
            $reason = $this->validate($voucher, $orderTotal);
            if ($reason !== null) {
                $errors[] = trans('messages.voucher.error_reason', ['code' => $code, 'reason' => $reason]);
                continue;
            }

            $available = (int) $voucher->availableBalance();
            $amount = min($available, max(0, $residual));
            $applied[] = ['voucher' => $voucher, 'amount' => $amount];
            $residual -= $amount;
        }

        return [
            'applied'        => $applied,
            'total_discount' => array_sum(array_column($applied, 'amount')),
            'errors'         => $errors,
        ];
    }

    public function listMyVouchers(string $email, int $orderTotal): Collection
    {
        $vouchers = $this->voucherRepo->listForEmail($email);

        return $vouchers->map(function (Voucher $voucher) use ($orderTotal) {
            $reason = $this->validate($voucher, max(1, $orderTotal));
            return VoucherDTO::fromModel(
                $voucher,
                redeemable: $reason === null,
                notRedeemableReason: $reason,
            );
        })->sortBy([
            ['redeemable', 'desc'],
        ])->values();
    }

    public function removeCode(string $code): void
    {
        $applied = $this->getAppliedCodes();
        $applied = array_values(array_filter($applied, fn ($c) => $c !== $code));
        session()->put(getCoreConfig('session.applied_vouchers'), $applied);
    }

    public function clearAll(): void
    {
        session()->forget(getCoreConfig('session.applied_vouchers'));
    }

    public function getAppliedCodes(): array
    {
        $codes = (array) session()->get(getCoreConfig('session.applied_vouchers'), []);
        return array_values(array_filter(array_map('strval', $codes), fn ($c) => trim($c) !== ''));
    }

    public function recordOrderVouchers(int $orderId, int $orderTotal): void
    {
        $result = $this->resolveApplied($orderTotal);
        if (empty($result['applied'])) {
            return;
        }

        $statusApplied = (int) getCoreConfig('voucher.history_status.applied');
        $userId = (int) getCurrentUserId() ?: null;

        foreach ($result['applied'] as $entry) {
            if ($entry['amount'] <= 0) {
                continue;
            }
            VoucherHistory::create([
                'voucher_id' => (int) $entry['voucher']->id,
                'order_id'   => $orderId,
                'user_id'    => $userId,
                'amount'     => (int) $entry['amount'],
                'status'     => $statusApplied,
            ]);
        }
    }

    public function confirmOrderVouchers(int $orderId): void
    {
        $statusApplied = (int) getCoreConfig('voucher.history_status.applied');
        $statusConfirmed = (int) getCoreConfig('voucher.history_status.confirmed');

        $rows = VoucherHistory::query()
            ->forOrder($orderId)
            ->where('status', $statusApplied)
            ->get(['id', 'voucher_id', 'amount']);
        if ($rows->isEmpty()) {
            return;
        }

        VoucherHistory::query()
            ->whereIn('id', $rows->pluck('id')->all())
            ->update(['status' => $statusConfirmed, 'updated_at' => now()]);

        foreach ($rows as $row) {
            DB::table('voucher')
                ->where('id', (int) $row->voucher_id)
                ->increment('redeemed_balance', (float) $row->amount);
        }

        $statusActive = (int) getCoreConfig('voucher.status.active');
        $statusFullyUsed = (int) getCoreConfig('voucher.status.fully_used');
        DB::table('voucher')
            ->whereIn('id', $rows->pluck('voucher_id')->unique()->all())
            ->where('status', $statusActive)
            ->whereColumn('redeemed_balance', '>=', 'amount')
            ->update(['status' => $statusFullyUsed]);
    }

    /**
     * Order cancel: flip history applied/confirmed → refunded + decrement
     * redeemed_balance + flip voucher back active (nếu trước đó fully_used).
     */
    public function revertOrderVouchers(int $orderId): void
    {
        $statusConfirmed = (int) getCoreConfig('voucher.history_status.confirmed');
        $statusRefunded = (int) getCoreConfig('voucher.history_status.refunded');

        $rows = VoucherHistory::query()
            ->forOrder($orderId)
            ->whereIn('status', [
                (int) getCoreConfig('voucher.history_status.applied'),
                $statusConfirmed,
            ])
            ->get(['id', 'voucher_id', 'amount', 'status']);
        if ($rows->isEmpty()) {
            return;
        }

        VoucherHistory::query()
            ->whereIn('id', $rows->pluck('id')->all())
            ->update(['status' => $statusRefunded, 'updated_at' => now()]);

        // Decrement balance CHỈ rows trước đó confirmed (applied chưa cộng).
        foreach ($rows->where('status', $statusConfirmed) as $row) {
            DB::table('voucher')
                ->where('id', (int) $row->voucher_id)
                ->where('redeemed_balance', '>=', (float) $row->amount)
                ->decrement('redeemed_balance', (float) $row->amount);
        }

        // Reactive voucher nếu trước đó fully_used.
        $statusActive = (int) getCoreConfig('voucher.status.active');
        $statusFullyUsed = (int) getCoreConfig('voucher.status.fully_used');
        DB::table('voucher')
            ->whereIn('id', $rows->pluck('voucher_id')->unique()->all())
            ->where('status', $statusFullyUsed)
            ->whereColumn('redeemed_balance', '<', 'amount')
            ->update(['status' => $statusActive]);
    }
}
