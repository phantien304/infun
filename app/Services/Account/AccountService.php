<?php

namespace App\Services\Account;

use App\Models\Entities\Orders;
use App\Models\Entities\User;
use App\Repositories\Interfaces\OrderRepositoryInterface;
use App\Repositories\Interfaces\UserPhoneRepositoryInterface;
use App\Repositories\Interfaces\UserRepositoryInterface;
use App\Services\Checkout\RefundService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Logic của các action profile / password / newsletter trên trang account.
 *
 * Tách khỏi controller vì:
 *  - update profile = transaction 2 bảng (user + user_phone).
 *  - change password yêu cầu re-hash + invalidate session-related cache.
 *  - newsletter là single-column update nhưng giữ cùng service để controller
 *    chỉ làm 1 dispatcher duy nhất.
 *
 * KHÔNG tự đoán is_verify khi update phone — caller trước đó đã giữ
 * `is_verify` trong $phoneData nếu cần (UserPhoneRepository::upsertForUser
 * cũng không reset, vì repo không biết user có đổi số hay không). Mặc định
 * AccountService giữ verified nếu phone không đổi (xem `updateProfile`).
 */
class AccountService
{
    public function __construct(
        protected UserRepositoryInterface $userRepo,
        protected UserPhoneRepositoryInterface $phoneRepo,
        protected OrderRepositoryInterface $orderRepo,
        protected RefundService $refundService,
    ) {
    }

    /**
     * Update profile + phone trong 1 transaction.
     *
     * Quy tắc is_verify:
     *  - User đổi số (number cũ khác mới) → reset is_verify = 0.
     *  - User giữ số → bảo toàn giá trị verified hiện tại.
     */
    public function updateProfile(int $userId, array $data): ?User
    {
        return DB::transaction(function () use ($userId, $data) {
            $user = $this->userRepo->updateProfile($userId, [
                'full_name' => $data['full_name'] ?? '',
                'sex'       => $data['sex']       ?? null,
                'address'   => $data['address']   ?? '',
            ]);
            if (! $user) {
                return null;
            }

            $newPhone = (string) ($data['phone'] ?? '');
            $existing = $this->phoneRepo->findForUser($userId);
            $verified = (bool) ($existing?->is_verify ?? false);
            $sameNumber = $existing !== null && (string) $existing->phone === $newPhone;

            $this->phoneRepo->upsertForUser($userId, [
                'phone'     => $newPhone,
                'is_verify' => ($sameNumber && $verified) ? 1 : 0,
            ]);

            return $user;
        });
    }

    /**
     * Đổi password. Validate `old_password` đã thực hiện ở
     * AccountChangePasswordRequest::withValidator — service chỉ hash + save.
     *
     * Sử dụng `lockForUpdate` trên row user để tránh race condition khi 2
     * tab đồng thời đổi password (hiếm nhưng có thể xảy ra với social login).
     */
    public function changePassword(int $userId, string $newPassword): ?User
    {
        return DB::transaction(function () use ($userId, $newPassword) {
            $user = User::query()->where('id', $userId)->lockForUpdate()->first();
            if (! $user) {
                return null;
            }
            $user->password = Hash::make($newPassword);
            $user->save();

            return $user;
        });
    }

    public function updateNewsletter(int $userId, bool $opted): ?User
    {
        return $this->userRepo->updateProfile($userId, [
            'newsletter' => $opted ? 1 : 0,
        ]);
    }

    /**
     * Huỷ đơn của user. Quy trình:
     *  1. Tìm order trong scope user. Không có → null (controller redirect).
     *  2. Nếu cần refund qua ZaloPay (đã thanh toán cổng) → gọi RefundService.
     *     Refund fail (return_code = 2) → throw để controller báo lỗi.
     *  3. Update status = `order_cancel_status_id`, gán `zp_refund_id` (mới
     *     hoặc giữ giá trị cũ).
     *  4. Append history + record orders_cancel.
     *
     * Toàn bộ wrap trong DB::transaction để rollback khi insert
     * orders_cancel fail giữa chừng (vẫn còn risk: refund đã gọi cổng mà
     * transaction rollback → ZaloPay đã refund nhưng DB chưa lưu refund_id.
     * Acceptable với volume thấp; có thể switch sang 2-phase commit sau).
     */
    public function cancelOrder(int $orderId, int $userId, string $reason, ?string $comment): ?Orders
    {
        $order = $this->orderRepo->getOrderForUser($orderId, $userId);
        if (! $order) {
            return null;
        }

        $refundId = $order->zp_refund_id;
        if ($this->refundService->needsRefund($order)) {
            [$ok, $newRefundId] = $this->refundService->refund($order, $reason);
            if (! $ok) {
                throw new \RuntimeException('refund_failed');
            }
            $refundId = $newRefundId;
        }

        return DB::transaction(function () use ($order, $reason, $comment, $refundId, $userId) {
            $cancelStatusId = (int) getConfigDb('order_cancel_status_id');
            $this->orderRepo->upsertOrder([
                'id'              => $order->id,
                'order_status_id' => $cancelStatusId,
                'zp_refund_id'    => $refundId,
            ]);
            $this->orderRepo->appendHistory($order->id, $cancelStatusId, $userId);
            $this->orderRepo->recordCancel($order->id, $userId, $reason, $comment);

            return $order->refresh();
        });
    }
}
