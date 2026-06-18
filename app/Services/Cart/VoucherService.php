<?php

namespace App\Services\Cart;

use App\Data\Output\VoucherDTO;
use App\Models\Entities\Voucher;
use App\Models\Entities\VoucherHistory;
use App\Repositories\Interfaces\VoucherRepositoryInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Voucher orchestration cho cart Shopee-style.
 *
 * Voucher = gift card cá nhân (KHÁC coupon marketing):
 *  - Trừ thẳng VND vào tổng đơn (không % không min subtotal).
 *  - Cho phép redeem từng phần — voucher 500k áp 1 order 300k còn dư 200k
 *    cho order sau.
 *  - 1 user có thể áp nhiều voucher cùng lúc (stack thẳng, cap ở
 *    `applyTotal <= orderTotal`).
 *
 * Session shape: `checkout.applied_vouchers` = ['CODE1', 'CODE2', ...]
 *
 * Lifecycle:
 *  - Apply cart    → session put codes, KHÔNG ghi DB (chỉ kiểm tra).
 *  - Build total   → re-validate, compute amount theo orderTotal residual.
 *  - Order create  → insert voucher_history rows status=applied, sau payment
 *                    callback đổi confirmed + update voucher.redeemed_balance.
 *  - Order cancel  → flip history status=refunded + decrement balance.
 */
class VoucherService
{
    public function __construct(
        protected VoucherRepositoryInterface $voucherRepo,
    ) {}

    /**
     * Áp 1 voucher code mới vào session — validate + append nếu OK.
     *
     * @return array{ok: bool, message: string}
     */
    public function applyCode(string $code, int $orderTotal): array
    {
        $code = trim($code);
        if ($code === '') {
            return ['ok' => false, 'message' => 'Vui lòng nhập mã voucher'];
        }

        $voucher = $this->voucherRepo->findByCode($code);
        if (! $voucher) {
            return ['ok' => false, 'message' => "Voucher '{$code}' không tồn tại"];
        }

        $reason = $this->validate($voucher, $orderTotal);
        if ($reason !== null) {
            return ['ok' => false, 'message' => "Voucher '{$code}': {$reason}"];
        }

        $applied = $this->getAppliedCodes();
        if (in_array($voucher->code, $applied, true)) {
            return ['ok' => false, 'message' => "Voucher '{$code}' đã được áp"];
        }

        $applied[] = $voucher->code;
        session()->put(getCoreConfig('session.applied_vouchers'), $applied);

        return ['ok' => true, 'message' => 'Đã áp voucher'];
    }

    /**
     * Validate voucher có redeemable hay không. Trả NULL nếu OK, reason string
     * nếu fail. KHÔNG tính amount ở đây — chỉ check eligibility.
     */
    public function validate(Voucher $voucher, int $orderTotal): ?string
    {
        $statusActive = (int) getCoreConfig('voucher.status.active');
        if ((int) $voucher->status !== $statusActive) {
            return 'Voucher không còn hiệu lực';
        }

        if ($voucher->date_expire && Carbon::parse($voucher->date_expire)->lt(Carbon::now()->startOfDay())) {
            return 'Voucher đã hết hạn';
        }

        $available = $voucher->availableBalance();
        if ($available <= 0) {
            return 'Voucher đã dùng hết';
        }

        if ($orderTotal <= 0) {
            return 'Đơn hàng không hợp lệ';
        }

        return null;
    }

    /**
     * Resolve session → array VoucherDTO + amount đã pro-rate theo orderTotal.
     *
     * Stacking + cap: voucher đầu trừ residual orderTotal; voucher kế tiếp trừ
     * (residual - voucher_1_amount). Khi residual = 0, các voucher còn lại
     * KHÔNG trừ thêm (vẫn được giữ trong applied, hiển thị amount=0).
     *
     * @return array{
     *     applied: array<int, array{voucher: Voucher, amount: int}>,
     *     total_discount: int,
     *     errors: array<int, string>,
     * }
     */
    public function resolveApplied(int $orderTotal): array
    {
        $codes = $this->getAppliedCodes();
        $applied = [];
        $errors = [];
        $residual = $orderTotal;

        // Batch 1 query thay vì findByCode mỗi code (resolveApplied chạy lại
        // mỗi lần build total → N+1 cộng dồn).
        $vouchers = $this->voucherRepo->findByCodes($codes);

        foreach ($codes as $code) {
            $voucher = $vouchers->get($code);
            if (! $voucher) {
                $errors[] = "Voucher '{$code}' không tồn tại";
                continue;
            }
            $reason = $this->validate($voucher, $orderTotal);
            if ($reason !== null) {
                $errors[] = "Voucher '{$code}': {$reason}";
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

    /**
     * List voucher của user (gửi tới email) → CollectionDTO sort: redeemable
     * trước, expired/used sau.
     *
     * @return Collection<int, VoucherDTO>
     */
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

    /**
     * @return array<int, string>
     */
    public function getAppliedCodes(): array
    {
        $codes = (array) session()->get(getCoreConfig('session.applied_vouchers'), []);
        return array_values(array_filter(array_map('strval', $codes), fn ($c) => trim($c) !== ''));
    }

    /**
     * Persist voucher_history rows khi order tạo. Status=applied (chưa
     * confirmed vì payment có thể fail). Gọi từ CreateOrderService.
     */
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

    /**
     * Sau payment success: flip applied → confirmed + cộng vào
     * voucher.redeemed_balance. Atomic UPDATE chống concurrency.
     */
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

        // Flip status=fully_used cho voucher đã dùng hết balance.
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
