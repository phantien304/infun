<?php

namespace App\Data\Output;

use App\Models\Entities\Orders;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Data;

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
        public Collection $items,
        public Collection $totals,
    ) {
    }

    public static function fromModel(Orders $order): self
    {
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

        $ordersProducts = $order->relationLoaded('ordersProducts') ? $order->ordersProducts : null;
        $productCount = $ordersProducts?->count() ?? 0;
        $firstProductName = $productCount > 0 ? (string) ($ordersProducts->first()->name ?? '') : '';

        $ordersTotals = $order->relationLoaded('ordersTotals') ? $order->ordersTotals : collect();
        $orderTotal = $ordersTotals->firstWhere('code', 'total');
        $total = (float) ($orderTotal?->value ?? $order->total ?? 0);
        $currencyCode = (string) ($order->currency_code ?? '');
        $currencyValue = (float) ($order->currency_value ?? 1);

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
            total: $total,
            totalLabel: moneyAtBuy($total, $currencyCode, $currencyValue),
            createdAt: $createdAt?->format('H:i m/d/Y') ?? '',
            createdAtFull: $createdAt?->format('Y-m-d H:i:s') ?? '',
            createdMinutesAgo: $createdMinutesAgo,
            firstProductName: $firstProductName,
            productCount: $productCount,
            isPaymentWaiting: $orderStatusId === $waitingStatusId,
            isPaymentSuccess: $orderStatusId === $successStatusId,
            canCancel: ! in_array($orderStatusId, array_map('intval', $notDelete), true),
            repaymentAllowed: $orderStatusId === $waitingStatusId && $createdMinutesAgo < 240,
            items: ($ordersProducts ?? collect())
                ->map(fn ($product) => OrderProductDTO::fromModel($product, $currencyCode, $currencyValue))
                ->values(),
            totals: $ordersTotals
                ->map(fn ($total) => OrderTotalDTO::fromModel($total, $currencyCode, $currencyValue))
                ->values(),
        );
    }
}
