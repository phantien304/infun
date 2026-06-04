<?php

namespace App\Repositories\Eloquent;

use App\Models\Entities\Orders;
use App\Models\Entities\OrdersCancel;
use App\Models\Entities\OrdersHistory;
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

    /**
     * Tra cứu order theo invoice_no — endpoint /order/search public, không
     * scope theo user. Eager-load đủ relations để view hiển thị 1 lần query.
     */
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
                'ordersProducts.product' => fn ($q) => $q->dateAvailable(),
                'ordersProducts.product.description',
            ])
            ->first();
    }

    /**
     * Order detail cho user đang đăng nhập — repayment, account detail.
     * Khoá 240 phút áp dụng cho repayment để chặn user repay order quá cũ
     * (xem CheckoutController::repayment).
     */
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

    /**
     * Tóm tắt order vừa thanh toán xong (trang success). Chỉ lấy 1 vài field
     * blade dùng — KHÔNG eager-load để nhẹ.
     */
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

    /**
     * Tạo / cập nhật order. Idempotent theo id nếu trong $data có 'id' tồn
     * tại (firstOrNew). KHÔNG bao DB::beginTransaction() ở đây — caller
     * (CreateOrderService) đã wrap transaction ngoài tổng.
     */
    public function upsertOrder(array $data): Orders
    {
        $order = $this->resetModel()
            ->where('id', (int) ($data['id'] ?? 0))
            ->firstOrNew();
        $order->fill($data)->save();

        return $order;
    }

    public function appendHistory(int $orderId, int $statusId, ?int $userId = null): OrdersHistory
    {
        return OrdersHistory::create([
            'order_id'        => $orderId,
            'order_status_id' => $statusId,
            'user_id'         => $userId,
        ]);
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

    /**
     * Phân trang order của user cho trang account.orders. Force scope
     * user_id qua `modifyBase` để không bị URL filter làm lệch (vd query
     * `filter[user_id]=...`).
     */
    public function getListForUser(int $userId, ?Request $request = null, ?int $perPage = null): LengthAwarePaginator
    {
        return $this->list(
            $request,
            $perPage,
            fn ($q) => $q->where('user_id', $userId)->orderBy('id', 'DESC'),
        );
    }

    /**
     * Detail order cho user — đầy đủ relations blade `account.order_detail`
     * cần. Tách khỏi `getOrderForUser` để chứa option `recentOnly` cho
     * repayment mà không ảnh hưởng trang detail.
     */
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
