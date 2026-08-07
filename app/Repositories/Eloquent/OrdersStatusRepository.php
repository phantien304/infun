<?php

namespace App\Repositories\Eloquent;

use App\Models\Entities\OrdersStatus;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Interfaces\OrdersStatusRepositoryInterface;
use Illuminate\Support\Collection;

/**
 * Bảng tham chiếu nhỏ (~20 dòng), chỉ Cms đọc để đổ dropdown — KHÔNG cache
 * (khác Zone/Carrier/Payment vốn còn được storefront đọc ở traffic cao).
 */
class OrdersStatusRepository extends QueryableRepository implements OrdersStatusRepositoryInterface
{
    public function model(): string
    {
        return OrdersStatus::class;
    }

    public function getAll(): Collection
    {
        return $this->resetModel()
            ->where('language_code', app()->getLocale())
            ->orderBy('id')
            ->get();
    }
}
