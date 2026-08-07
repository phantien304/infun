<?php

namespace App\Repositories\Eloquent;

use App\Models\Entities\Orders;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Interfaces\OrderCmsRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

/**
 * listForCms/getForCms hand-roll (không dùng QueryableRepository::list() —
 * Spatie QueryBuilder) — giống hệt convention ProductCmsRepository, để khớp
 * đúng field cũ bên Vue (id_eq, invoice_no_cons, full_name_cons, ...).
 */
class OrderCmsRepository extends QueryableRepository implements OrderCmsRepositoryInterface
{
    public function model(): string
    {
        return Orders::class;
    }

    protected function cmsDetailRelations(): array
    {
        return [
            'ordersProducts.ordersProductOptions',
            'ordersHistories' => fn ($q) => $q->orderBy('created_at', 'desc'),
            'ordersHistories.ordersStatus',
            'ordersHistories.user',
            'ordersTotals' => fn ($q) => $q->orderBy('sort_order'),
            'ordersStatus',
            'carrier',
            'payment.description',
            'user',
        ];
    }

    public function listForCms(Request $request): LengthAwarePaginator
    {
        $order   = strtolower((string) $request->input('order', 'desc')) === 'asc' ? 'asc' : 'desc';
        $deleted = (int) $request->input('deleted_at', -1);
        $perPage = max(1, (int) $request->input('per_page', 50));

        $sortMap = [
            'id'              => 'orders.id',
            'invoice_no'      => 'orders.invoice_no',
            'full_name'       => 'orders.full_name',
            'order_status_id' => 'orders.order_status_id',
            'total'           => 'orders.total',
            'created_at'      => 'orders.created_at',
        ];
        $sortField  = (string) $request->input('sort', 'id');
        $sortColumn = $sortMap[$sortField] ?? 'orders.id';

        $query = Orders::query()->with(['ordersStatus']);

        if ($deleted === 0) {
            $query->onlyTrashed();
        } elseif ($deleted === -1) {
            $query->withTrashed();
        }

        if ($request->filled('id_eq')) {
            $query->where('orders.id', (int) $request->input('id_eq'));
        }
        // invoice_no là chuỗi hex (vd "6A3211C5DD244"), KHÔNG phải số — mt219
        // gốc dùng parseInt() cho field này (bug: luôn NaN/rỗng với hầu hết
        // invoice_no thật). Sửa đúng: like chuỗi con.
        if ($request->filled('invoice_no_cons')) {
            $query->where('orders.invoice_no', 'like', '%' . trim((string) $request->input('invoice_no_cons')) . '%');
        }
        if ($request->filled('full_name_cons')) {
            $query->where('orders.full_name', 'like', '%' . trim((string) $request->input('full_name_cons')) . '%');
        }
        if ($request->filled('order_status_id_eq')) {
            $query->where('orders.order_status_id', (int) $request->input('order_status_id_eq'));
        }
        if ($request->filled('total_eq')) {
            $query->where('orders.total', (float) $request->input('total_eq'));
        }
        if ($request->filled('created_at_cons')) {
            $query->whereDate('orders.created_at', (string) $request->input('created_at_cons'));
        }

        $query->orderBy($sortColumn, $order);

        return $query->paginate($perPage);
    }

    public function getForCms(int $id): ?Orders
    {
        return $this->resetModel()->withTrashed()->with($this->cmsDetailRelations())->find($id);
    }

    public function deleteByIds(array $ids): int
    {
        return $this->resetModel()->whereIn('id', $ids)->delete();
    }

    public function restoreByIds(array $ids): int
    {
        return $this->resetModel()->withTrashed()->whereIn('id', $ids)->restore();
    }

    public function restoreById(int $id): ?Orders
    {
        $order = $this->resetModel()->withTrashed()->find($id);
        $order?->restore();

        return $order;
    }
}
