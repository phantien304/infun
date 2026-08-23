<?php

namespace App\Repositories\Interfaces;

use App\Models\Entities\Carrier;
use App\Repositories\Base\BaseRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface CarrierRepositoryInterface extends BaseRepositoryInterface
{
    public function listAllCached(): Collection;

    // ----- CMS (admin) -----

    public function listForCms(Request $request): LengthAwarePaginator;

    public function getForCms(int $id): ?Carrier;

    public function saveFromCms(?Carrier $carrier, array $data): Carrier;

    /**
     * Xoá qua vòng lặp model (KHÔNG mass-delete builder) — Carrier có
     * $destroyRelations = ['carrierOrderStatus'], cascade chỉ chạy qua event
     * `deleting` của TỪNG model instance (cùng loại bug đã gặp ở
     * ReviewRepository::deleteByIds() / OrdersStatusRepository::deleteByIds()).
     */
    public function deleteByIds(array $ids): int;

    public function restoreByIds(array $ids): int;

    public function restoreById(int $id): ?Carrier;
}
