<?php

namespace App\Repositories\Eloquent;

use App\Models\Entities\Orders;
use App\Models\Entities\OrdersCancel;
use App\Models\Entities\OrdersHistory;
use App\Models\Entities\OrdersProduct;
use App\Models\Entities\OrdersProductOption;
use App\Models\Entities\OrdersTotal;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Interfaces\OrderRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

class OrderRepository extends QueryableRepository implements OrderRepositoryInterface
{
    public function model(): string
    {
        return Orders::class;
    }

    public function getOrderByInvoiceNo(?string $invoiceNo): ?Orders
    {
        if (! filled($invoiceNo)) {
            return null;
        }

        return $this->resetModel()
            ->where('invoice_no', $invoiceNo)
            ->with([
                'ordersStatus',
                'ordersHistories' => fn ($q) => $q->orderBy('created_at', 'DESC'),
                'ordersHistories.ordersStatus',
                'ordersProducts.ordersProductOptions',
                'ordersProducts.product',
                'ordersProducts.product.description',
            ])
            ->first();
    }

    public function getOrderForUser(int $orderId, int $userId, bool $recentOnly = false): ?Orders
    {
        $q = $this->resetModel()
            ->where('id', $orderId)
            ->where('user_id', $userId)
            ->with([
                'ordersProducts.ordersProductOptions',
                'ordersProducts.product' => fn ($q) => $q->dateAvailable(),
                'ordersProducts.product.description',
                'ordersTotals' => fn ($q) => $q->orderBy('sort_order', 'ASC'),
                'carrier',
                'payment.description',
            ]);

        if ($recentOnly) {
            $q->where('created_at', '>', Carbon::now()->subMinutes(240));
        }

        return $q->orderBy('created_at', 'DESC')->first();
    }

    public function getOrderSummary(int $orderId): ?Orders
    {
        if ($orderId <= 0) {
            return null;
        }

        return $this->resetModel()
            ->where('id', $orderId)
            ->select('id', 'invoice_no', 'full_name', 'email', 'order_status_id', 'telephone')
            ->first();
    }

    public function findByAppTransId(string $appTransId): ?Orders
    {
        return $this->resetModel()->where('app_trans_id', $appTransId)->first();
    }

    public function findByIdempotencyKey(string $key): ?Orders
    {
        if (! filled($key)) {
            return null;
        }

        return $this->resetModel()->where('idempotency_key', $key)->first();
    }

    public function upsertOrder(array $data): Orders
    {
        $order = $this->resetModel()
            ->where('id', (int) ($data['id'] ?? 0))
            ->firstOrNew();
        $order->fill($data)->save();

        return $order;
    }

    public function appendHistory(int $orderId, int $statusId, ?int $userId = null, ?string $comment = null, bool $notify = false): OrdersHistory
    {
        return OrdersHistory::create([
            'order_id'        => $orderId,
            'order_status_id' => $statusId,
            'user_id'         => $userId,
            'comment'         => $comment,
            'notify'          => $notify,
        ]);
    }

    public function createOrderItem(array $productData, array $optionsData): OrdersProduct
    {
        $orderProduct = OrdersProduct::create($productData);

        foreach ($optionsData as $opt) {
            OrdersProductOption::create($opt + [
                'order_id'         => $orderProduct->order_id,
                'order_product_id' => $orderProduct->id,
            ]);
        }

        return $orderProduct;
    }

    public function createOrderTotal(array $data): OrdersTotal
    {
        return OrdersTotal::create($data);
    }

    public function ensureHistory(int $orderId, int $statusId): void
    {
        $exists = OrdersHistory::where('order_id', $orderId)
            ->where('order_status_id', $statusId)
            ->exists();
        if (! $exists) {
            $this->appendHistory($orderId, $statusId);
        }
    }

    protected function withRelations(): array
    {
        return [
            'ordersProducts',
            'ordersTotal' => fn ($q) => $q->where('code', 'total'),
            'ordersStatus',
        ];
    }

    public function getListForUser(int $userId, ?Request $request = null, ?int $perPage = null): LengthAwarePaginator
    {
        return $this->list(
            $request,
            $perPage,
            fn ($q) => $q->where('user_id', $userId)->orderBy('id', 'DESC'),
        );
    }

    public function getDetailForUser(int $orderId, int $userId): ?Orders
    {
        return $this->resetModel()
            ->where('id', $orderId)
            ->where('user_id', $userId)
            ->with([
                'ordersProducts.ordersProductOptions',
                'ordersProducts.product' => fn ($q) => $q->dateAvailable(),
                'ordersProducts.product.description',
                'ordersTotals' => fn ($q) => $q->orderBy('sort_order', 'ASC'),
                'carrier',
                'payment.description',
                'ordersStatus',
            ])
            ->orderBy('created_at', 'DESC')
            ->first();
    }

    public function recordCancel(int $orderId, int $userId, string $reason, ?string $comment): OrdersCancel
    {
        return OrdersCancel::create([
            'order_id'      => $orderId,
            'user_id'       => $userId,
            'return_reason' => $reason,
            'comment'       => $comment,
        ]);
    }
}
