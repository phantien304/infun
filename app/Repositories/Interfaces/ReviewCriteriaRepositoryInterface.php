<?php

namespace App\Repositories\Interfaces;

use App\Models\Entities\ReviewCriteria;
use App\Repositories\Base\BaseRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

interface ReviewCriteriaRepositoryInterface extends BaseRepositoryInterface
{
    public function idsByCodes(array $codes): Collection;

    // ----- CMS (admin) -----
    public function listForCms(Request $request): LengthAwarePaginator;

    public function getForCms(int $id): ?ReviewCriteria;

    public function saveFromCms(?ReviewCriteria $criteria, array $data): ReviewCriteria;

    public function deleteByIds(array $ids): int;

    public function restoreByIds(array $ids): int;

    public function restoreById(int $id): ?ReviewCriteria;
}
