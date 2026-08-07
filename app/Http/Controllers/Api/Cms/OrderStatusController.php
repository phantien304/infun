<?php

namespace App\Http\Controllers\Api\Cms;

use App\Repositories\Interfaces\OrdersStatusRepositoryInterface;

/**
 * OrdersStatus — CHỈ read (dropdown "Tình trạng đơn hàng" ở order/form.jsx,
 * order/view.jsx, order/index.jsx bộ lọc). CRUD đầy đủ là màn hình riêng,
 * chưa convert (router 'order-status' phía infuncms đang placeholder).
 * Permission slug 'order-status' khớp sp_permissions có sẵn (list-order-status).
 */
class OrderStatusController extends BaseCmsController
{
    protected string $permission = 'order-status';

    public function __construct(
        private readonly OrdersStatusRepositoryInterface $repo
    ) {
    }

    public function index()
    {
        $data = $this->repo->getAll()
            ->map(fn ($s) => ['id' => $s->id, 'name' => $s->name])
            ->values();

        return response()->json(['data' => $data]);
    }
}
