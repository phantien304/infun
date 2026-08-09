<?php

namespace App\Repositories\Eloquent;

use App\Models\Entities\OrdersStatus;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Interfaces\OrdersStatusRepositoryInterface;
use Illuminate\Support\Collection;

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
