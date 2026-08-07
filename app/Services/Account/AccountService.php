<?php

namespace App\Services\Account;

use App\Models\Entities\Orders;
use App\Models\Entities\User;
use App\Repositories\Interfaces\OrderRepositoryInterface;
use App\Repositories\Interfaces\UserPhoneRepositoryInterface;
use App\Repositories\Interfaces\UserRepositoryInterface;
use App\Services\Checkout\RefundService;
use Illuminate\Support\Facades\Auth;

class AccountService
{
    public function __construct(
        protected UserRepositoryInterface $userRepo,
        protected UserPhoneRepositoryInterface $phoneRepo,
        protected OrderRepositoryInterface $orderRepo,
        protected RefundService $refundService,
        protected \App\Services\Checkout\PromotionService $promotions,
    ) {
    }

    public function updateProfile(int $userId, array $data): ?User
    {
        return $this->userRepo->transaction(function () use ($userId, $data) {
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

    public function changePassword(int $userId, string $newPassword): ?User
    {
        $user = $this->userRepo->transaction(
            fn () => $this->userRepo->updatePasswordById($userId, $newPassword),
        );

        if ($user) {
            Auth::setUser($user);
            Auth::logoutOtherDevices($newPassword);
        }

        return $user;
    }

    public function updateNewsletter(int $userId, bool $opted): ?User
    {
        return $this->userRepo->updateProfile($userId, [
            'newsletter' => $opted ? 1 : 0,
        ]);
    }

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

        return $this->orderRepo->transaction(function () use ($order, $reason, $comment, $refundId, $userId) {
            $cancelStatusId = (int) getConfigDb('order_cancel_status_id');
            $this->orderRepo->upsertOrder([
                'id'              => $order->id,
                'order_status_id' => $cancelStatusId,
                'zp_refund_id'    => $refundId,
            ]);
            $this->orderRepo->appendHistory($order->id, $cancelStatusId, $userId);
            $this->orderRepo->recordCancel($order->id, $userId, $reason, $comment);

            $this->promotions->revertForOrder((int) $order->id);

            return $order->refresh();
        });
    }
}
