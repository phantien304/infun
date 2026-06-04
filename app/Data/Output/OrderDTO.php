<?php

namespace App\Data\Output;

use App\Models\Entities\Orders;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;

/**
 * DTO order cho trang `account.orders` (list) + `account.detailOrder` (detail).
 *
 * Tính 2 cờ derived ở DTO để blade chỉ check boolean, không phải đối chiếu
 * config:
 *  - `isPaymentWaiting` — order đang ở status WAITING (cần thanh toán lại).
 *  - `isPaymentSuccess` — order đã thanh toán xong.
 *  - `canCancel` — order_status_id KHÔNG nằm trong
 *    `config_order_member_not_delete` (bảng config: status nào user không
 *    được tự huỷ).
 *  - `repaymentAllowed` — order WAITING + còn trong window 240 phút từ
 *    created_at (mirror logic legacy).
 *
 * `totalLabel` cho list = giá trị dòng `total` cuối cùng (label "Tổng tiền").
 * Cố gắng lấy từ `ordersTotal` (singular, eager-load relation) hoặc fallback
 * sang `total` column trên orders.
 */
class OrderDTO extends Data
{
    public function __construct(
        public int $id,
        public string $invoiceNo,
        public string $invoicePrefix,
        public string $fullName,
        public string $email,
        public string $telephone,
        public string $address,
        public string $ward,
        public string $district,
        public string $zone,
        public int $orderStatusId,
        public string $orderStatusName,
        public string $paymentCode,
        public string $paymentName,
        public string $carrierName,
        public ?string $zpRefundId,
        public ?string $zpTransId,
        public ?string $appTransId,
        public float $total,
        public string $totalLabel,
        public string $createdAt,
        public string $createdAtFull,
        public int $createdMinutesAgo,
        public string $firstProductName,
        public int $productCount,
        public bool $isPaymentWaiting,
        public bool $isPaymentSuccess,
        public bool $canCancel,
        public bool $repaymentAllowed,
        #[DataCollectionOf(OrderItemDTO::class)]
        public Collection $items,
        #[DataCollectionOf(OrderTotalDTO::class)]
        public Collection $totals,
    ) {
    }

    public static function fromModel(Orders $order): self
    {
        $currency = (string) getConfigDb('config_currency');
        $waitingStatusId = (int) getConfigDb('order_payment_waiting_status_id');
        $successStatusId = (int) getConfigDb('order_payment_success_status_id');
        $notDelete = (array) getConfigDb('config_order_member_not_delete');

        $createdAt = $order->created_at;
        $createdMinutesAgo = $createdAt ? (int) $createdAt->diffInMinutes() : PHP_INT_MAX;

        $statusName = '';
        if ($order->relationLoaded('ordersStatus') && $order->ordersStatus) {
            $statusName = (string) ($order->ordersStatus->name ?? '');
        }

        $paymentName = '';
        if ($order->relationLoaded('payment') && $order->payment) {
            $payment = $order->payment;
            if ($payment->relationLoaded('description') && $payment->description) {
                $paymentName = (string) ($payment->description->name ?? '');
            }
        }

        $carrierName = '';
        if ($order->relationLoaded('carrier') && $order->carrier) {
            $carrierName = (string) ($order->carrier->name ?? '');
        }

        // First product name + count cho list view.
        $items = $order->relationLoaded('ordersProducts') ? $order->ordersProducts : null;
        $productCount = $items?->count() ?? 0;
        $firstProductName = $productCount > 0 ? (string) ($items->first()->name ?? '') : '';

        // Tổng tiền — ưu tiên row `code = total` trong ordersTotals; fallback
        // sang column `total` của Orders.
        $totalsCollection = $order->relationLoaded('ordersTotals') ? $order->ordersTotals : collect();
        $totalRow = $totalsCollection->firstWhere('code', 'total');
        $totalValue = (float) ($totalRow?->value ?? $order->total ?? 0);

        $orderStatusId = (int) ($order->order_status_id ?? 0);

        return new self(
            id: (int) $order->id,
            invoiceNo: (string) ($order->invoice_no ?? ''),
            invoicePrefix: (string) ($order->invoice_prefix ?? ''),
            fullName: (string) ($order->full_name ?? ''),
            email: (string) ($order->email ?? ''),
            telephone: (string) ($order->telephone ?? ''),
            address: (string) ($order->address ?? ''),
            ward: (string) ($order->ward ?? ''),
            district: (string) ($order->district ?? ''),
            zone: (string) ($order->zone ?? ''),
            orderStatusId: $orderStatusId,
            orderStatusName: $statusName,
            paymentCode: (string) ($order->payment_code ?? ''),
            paymentName: $paymentName,
            carrierName: $carrierName,
            zpRefundId: $order->zp_refund_id ?? null,
            zpTransId: $order->zp_trans_id ?? null,
            appTransId: $order->app_trans_id ?? null,
            total: $totalValue,
            totalLabel: number_format($totalValue, 0, '', ',') . $currency,
            createdAt: $createdAt?->format('H:i m/d/Y') ?? '',
            createdAtFull: $createdAt?->format('Y-m-d H:i:s') ?? '',
            createdMinutesAgo: $createdMinutesAgo,
            firstProductName: $firstProductName,
            productCount: $productCount,
            isPaymentWaiting: $orderStatusId === $waitingStatusId,
            isPaymentSuccess: $orderStatusId === $successStatusId,
            canCancel: ! in_array($orderStatusId, array_map('intval', $notDelete), true),
            repaymentAllowed: $orderStatusId === $waitingStatusId && $createdMinutesAgo < 240,
            items: OrderItemDTO::collect($items ?? collect()),
            totals: OrderTotalDTO::collect($totalsCollection),
        );
    }
}
