<?php

namespace App\Services\Cart;

use App\Data\Output\VoucherDTO;
use App\Models\Entities\Voucher;
use App\Repositories\Interfaces\VoucherHistoryRepositoryInterface;
use App\Repositories\Interfaces\VoucherRepositoryInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class VoucherService
{
    public function __construct(
        protected VoucherRepositoryInterface $voucherRepo,
        protected VoucherHistoryRepositoryInterface $voucherHistoryRepo,
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
            $this->voucherHistoryRepo->record(
                (int) $entry['voucher']->id,
                $orderId,
                $userId,
                (int) $entry['amount'],
                $statusApplied,
            );
        }
    }

    public function confirmOrderVouchers(int $orderId): void
    {
        $statusApplied = (int) getCoreConfig('voucher.history_status.applied');
        $statusConfirmed = (int) getCoreConfig('voucher.history_status.confirmed');

        $rows = $this->voucherHistoryRepo->forOrderByStatus($orderId, $statusApplied);
        if ($rows->isEmpty()) {
            return;
        }

        $this->voucherHistoryRepo->markStatus($rows->pluck('id')->all(), $statusConfirmed);

        foreach ($rows as $row) {
            // Conditional: chỉ trừ khi số dư còn đủ — 2 đơn cùng tiêu một
            // voucher thì đơn confirm sau bị chặn (không vượt trần amount).
            // KHÔNG throw vì tới đây thanh toán đã xong — log để CS xử lý
            // (đơn được giảm giá nhưng voucher không còn đủ số dư).
            $affected = $this->voucherRepo->incrementRedeemed((int) $row->voucher_id, (float) $row->amount);
            if ($affected === 0) {
                logError(sprintf(
                    'confirmOrderVouchers: voucher %d insufficient balance for order %d (amount %s) — double-spend blocked, needs CS review',
                    (int) $row->voucher_id,
                    $orderId,
                    (string) $row->amount,
                ));
            }
        }

        $statusActive = (int) getCoreConfig('voucher.status.active');
        $statusFullyUsed = (int) getCoreConfig('voucher.status.fully_used');
        $this->voucherRepo->markFullyUsed(
            $rows->pluck('voucher_id')->unique()->all(),
            $statusActive,
            $statusFullyUsed,
        );
    }

    public function revertOrderVouchers(int $orderId): void
    {
        $statusConfirmed = (int) getCoreConfig('voucher.history_status.confirmed');
        $statusRefunded = (int) getCoreConfig('voucher.history_status.refunded');

        $rows = $this->voucherHistoryRepo->forOrderByStatuses($orderId, [
            (int) getCoreConfig('voucher.history_status.applied'),
            $statusConfirmed,
        ]);
        if ($rows->isEmpty()) {
            return;
        }

        $this->voucherHistoryRepo->markStatus($rows->pluck('id')->all(), $statusRefunded);

        foreach ($rows->where('status', $statusConfirmed) as $row) {
            $this->voucherRepo->decrementRedeemed((int) $row->voucher_id, (float) $row->amount);
        }

        $statusActive = (int) getCoreConfig('voucher.status.active');
        $statusFullyUsed = (int) getCoreConfig('voucher.status.fully_used');
        $this->voucherRepo->reactivateVouchers(
            $rows->pluck('voucher_id')->unique()->all(),
            $statusFullyUsed,
            $statusActive,
        );
    }
}
