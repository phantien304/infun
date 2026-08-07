<?php

namespace App\Repositories\Interfaces;

use App\Models\Entities\Orders;
use App\Repositories\Base\BaseRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

/**
 * Order — đọc/xoá/khôi phục RIÊNG cho Cms (tách khỏi OrderRepositoryInterface
 * dùng cho storefront/checkout — giống ProductCmsRepositoryInterface tách khỏi
 * ProductRepositoryInterface). Ghi dữ liệu (upsert/history/item/total) vẫn
 * dùng chung OrderRepositoryInterface — xem OrderAdminWriteService.
 */
interface OrderCmsRepositoryInterface extends BaseRepositoryInterface
{
    public function listForCms(Request $request): LengthAwarePaginator;

    public function getForCms(int $id): ?Orders;

    public function deleteByIds(array $ids): int;

    public function restoreByIds(array $ids): int;

    public function restoreById(int $id): ?Orders;
}
