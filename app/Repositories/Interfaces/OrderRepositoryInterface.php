<?php

namespace App\Repositories\Interfaces;

use App\Models\Entities\Orders;
use App\Models\Entities\OrdersCancel;
use App\Models\Entities\OrdersHistory;
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

    public function ensureHistory(int $orderId, int $statusId): void;

    /**
     * Phân trang order của 1 user — dùng cho trang `account.orders`.
     * Tận dụng nguyên pipeline QueryableRepository (Spatie filter / sort /
     * appends query string) nhưng force `where user_id` qua modifyBase
     * closure để KHÔNG lệ thuộc filter từ URL → không bị user request thiếu
     * scope.
     */
    public function getListForUser(int $userId, ?Request $request = null, ?int $perPage = null): LengthAwarePaginator;

    /**
     * Detail order cho trang `account.detailOrder`. Khác `getOrderForUser`
     * ở chỗ eager-load đầy đủ payment / ordersTotals / carrier (đủ render
     * blade), KHÔNG bị giới hạn `created_at > now-240m` như repayment.
     */
    public function getDetailForUser(int $orderId, int $userId): ?Orders;

    /**
     * Ghi log huỷ đơn — bảng `orders_cancel`. Tách khỏi `cancelOrder` vì
     * `cancelOrder` còn cập nhật status + zp_refund_id trên order chính.
     */
    public function recordCancel(int $orderId, int $userId, string $reason, ?string $comment): OrdersCancel;
}
