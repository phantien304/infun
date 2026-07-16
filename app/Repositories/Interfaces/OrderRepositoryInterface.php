<?php

namespace App\Repositories\Interfaces;

use App\Models\Entities\Orders;
use App\Models\Entities\OrdersCancel;
use App\Models\Entities\OrdersHistory;
use App\Models\Entities\OrdersProduct;
use App\Models\Entities\OrdersTotal;
use App\Repositories\Base\BaseRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

interface OrderRepositoryInterface extends BaseRepositoryInterface
{
    public function getOrderByInvoiceNo(?string $invoiceNo): ?Orders;

    public function getOrderForUser(int $orderId, int $userId, bool $recentOnly = false): ?Orders;

    public function getOrderSummary(int $orderId): ?Orders;

    public function findByAppTransId(string $appTransId): ?Orders;

    public function findByIdempotencyKey(string $key): ?Orders;

    public function upsertOrder(array $data): Orders;

    public function appendHistory(int $orderId, int $statusId, ?int $userId = null): OrdersHistory;

    public function createOrderItem(array $productData, array $optionsData): OrdersProduct;

    public function createOrderTotal(array $data): OrdersTotal;

    public function ensureHistory(int $orderId, int $statusId): void;

    public function getListForUser(int $userId, ?Request $request = null, ?int $perPage = null): LengthAwarePaginator;

    public function getDetailForUser(int $orderId, int $userId): ?Orders;

    public function recordCancel(int $orderId, int $userId, string $reason, ?string $comment): OrdersCancel;
}
