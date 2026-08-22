<?php

namespace App\Repositories\Interfaces;

use App\Models\Entities\Filter;
use App\Repositories\Base\BaseRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

interface FilterRepositoryInterface extends BaseRepositoryInterface
{
    public function listWithValues(): Collection;

    public function listAllCached(): Collection;

    // ----- CMS (admin) -----
    public function listForCms(Request $request): LengthAwarePaginator;

    public function getForCms(int $id): ?Filter;

    public function saveFromCms(?Filter $filter, array $data): Filter;

    public function deleteByIds(array $ids): int;

    public function restoreByIds(array $ids): int;

    public function restoreById(int $id): ?Filter;
}
