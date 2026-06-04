<?php

namespace App\Services\Checkout;

use App\Helpers\ZaloPay;
use App\Models\Entities\Orders;

/**
 * Wrapper riêng cho ZaloPay refund — tách khỏi CheckoutPaymentService vì
 * CheckoutPaymentService chỉ phục vụ tạo đơn / repayment / IPN, còn refund
 * sinh ra từ trang `account.detailOrder` khi user huỷ đơn đã thanh toán.
 *
 * Stateless. Caller (AccountService::cancelOrder) chịu trách nhiệm:
 *  1. Quyết định order có cần refund hay không (xem `needsRefund`).
 *  2. Persist `zp_refund_id` về DB qua OrderRepository::upsertOrder.
 *
 * Service KHÔNG ghi DB → dễ test, dễ retry.
 */
class RefundService
{
    public function __construct(
        protected ZaloPay $zaloPay,
    ) {
    }

    /**
     * Order có cần refund qua ZaloPay không. Điều kiện:
     *  - Có `zp_trans_id` (đã thanh toán cổng — ngược với cod).
     *  - Chưa có `zp_refund_id` (chưa refund lần nào).
     */
    public function needsRefund(Orders $order): bool
    {
        return filled($order->zp_trans_id) && ! filled($order->zp_refund_id);
    }

    /**
     * Thực thi refund. Trả `[true, mRefundId]` khi thành công, `[false, null]`
     * khi ZaloPay trả `return_code = 2` hoặc response rỗng.
     */
    public function refund(Orders $order, string $description): array
    {
        $payload = $this->zaloPay->buildRefundData([
            'zp_trans_id' => $order->zp_trans_id,
            'amount'      => (int) $order->total,
            'description' => $description,
        ]);
        $response = $this->zaloPay->refund($payload);

        if (empty($response) || (int) ($response['return_code'] ?? 0) === 2) {
            return [false, null];
        }

        return [true, (string) ($response['refund_id'] ?? $payload['m_refund_id'] ?? '')];
    }

    /**
     * Dùng cho trang chi tiết order — hiển thị trạng thái refund
     * cho user. ZaloPay trả `return_code` (1 = thành công, 2 = thất bại,
     * 3 = đang xử lý). Service trả enum-like string để blade dễ render.
     */
    public function getStatus(string $mRefundId): string
    {
        if (! filled($mRefundId)) {
            return 'none';
        }
        $response = $this->zaloPay->getRefundStatus($mRefundId);
        $code = (int) ($response['return_code'] ?? 0);

        return match ($code) {
            1       => 'success',
            2, 3    => 'processing',
            default => 'unknown',
        };
    }
}
