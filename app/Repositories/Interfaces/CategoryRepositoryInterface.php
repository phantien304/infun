<?php

namespace App\Repositories\Interfaces;

use App\Models\Entities\Category;
use App\Repositories\Base\BaseRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

interface CategoryRepositoryInterface extends BaseRepositoryInterface
{
    public function listAllCached(): Collection;

    public function getCategoryDetail(int $id): ?Category;

    // ----- CMS (admin) -----
    public function listForCms(Request $request): LengthAwarePaginator;

    public function getForCms(int $id): ?Category;

    public function saveFromCms(?Category $category, array $data): Category;

    public function deleteByIds(array $ids): int;

    public function restoreByIds(array $ids): int;

    public function restoreById(int $id): ?Category;
}
