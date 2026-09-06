<?php

namespace App\Repositories\Interfaces;

use App\Models\Entities\Skincare;
use App\Repositories\Base\BaseRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

interface SkincareRepositoryInterface extends BaseRepositoryInterface
{
    public function listForCms(Request $request): LengthAwarePaginator;

    public function getForCms(int $id): ?Skincare;

    public function saveFromCms(?Skincare $skincare, array $data): Skincare;

    public function deleteByIds(array $ids): int;

    public function restoreByIds(array $ids): int;

    public function restoreById(int $id): ?Skincare;
}
