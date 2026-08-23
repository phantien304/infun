<?php

namespace App\Repositories\Interfaces;

use App\Models\Entities\CarrierOrderStatus;
use App\Repositories\Base\BaseRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

interface CarrierOrderStatusRepositoryInterface extends BaseRepositoryInterface
{
    public function listForCms(Request $request): LengthAwarePaginator;

    public function getForCms(int $id): ?CarrierOrderStatus;

    /**
     * `description` là 1 field ĐƠN (không theo ngôn ngữ) — bảng thật
     * carrier_order_status_description chỉ có PK là carrier_order_status_id
     * (KHÔNG composite với language_code như model CarrierOrderStatusDescription
     * khai báo — model đó khai `$primaryKey` dạng mảng, Eloquent gốc không
     * hỗ trợ composite PK kiểu này nên model chưa từng dùng được đúng; bảng
     * DB chỉ chứa tối đa 1 dòng/status). Repo này bypass model đó, upsert
     * thẳng qua query builder cho khớp cấu trúc bảng thật.
     */
    public function saveFromCms(?CarrierOrderStatus $status, array $data): CarrierOrderStatus;

    /**
     * Xoá qua vòng lặp model (KHÔNG mass-delete builder) — CarrierOrderStatus
     * có $destroyRelations = ['ordersStatusCarrierOrders'], cascade chỉ chạy
     * qua event `deleting` của TỪNG model instance.
     */
    public function deleteByIds(array $ids): int;

    public function restoreByIds(array $ids): int;

    public function restoreById(int $id): ?CarrierOrderStatus;
}
