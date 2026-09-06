<?php

namespace App\Repositories\Interfaces;

use App\Models\Entities\BlogCategory;
use App\Repositories\Base\BaseRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

interface BlogCategoryRepositoryInterface extends BaseRepositoryInterface
{
    public function listAllCached(): Collection;

    public function findWithDescription(int|string $id): ?BlogCategory;

    // ===================== CMS (admin) =====================

    public function listForCms(Request $request): LengthAwarePaginator;

    public function getForCms(int $id): ?BlogCategory;

    public function saveFromCms(?BlogCategory $category, array $data): BlogCategory;

    public function deleteByIds(array $ids): int;

    public function restoreByIds(array $ids): int;

    public function restoreById(int $id): ?BlogCategory;
}
