<?php

namespace App\Services\Checkout;

use App\Models\Entities\Orders;
use App\Services\Payment\ZaloPayService;

class RefundService
{
    public function __construct(
        protected ZaloPayService $zaloPayService,
    ) {
    }

    public function needsRefund(Orders $order): bool
    {
        return filled($order->zp_trans_id) && ! filled($order->zp_refund_id);
    }

    public function refund(Orders $order, string $description): array
    {
        $payload = $this->zaloPayService->buildRefundData([
            'zp_trans_id' => $order->zp_trans_id,
            'amount'      => (int) $order->total,
            'description' => $description,
        ]);
        $response = $this->zaloPayService->refund($payload);

        if (empty($response) || (int) ($response['return_code'] ?? 0) === 2) {
            return [false, null];
        }

        return [true, (string) ($response['refund_id'] ?? $payload['m_refund_id'] ?? '')];
    }

    public function getStatus(string $mRefundId): string
    {
        if (! filled($mRefundId)) {
            return 'none';
        }
        $response = $this->zaloPayService->getRefundStatus($mRefundId);
        $code = (int) ($response['return_code'] ?? 0);

        return match ($code) {
            1       => 'success',
            2, 3    => 'processing',
            default => 'unknown',
        };
    }
}
