<?php

namespace App\Repositories\Interfaces;

use App\Models\Entities\Safety;
use App\Repositories\Base\BaseRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

interface SafetyRepositoryInterface extends BaseRepositoryInterface
{
    public function listForCms(Request $request): LengthAwarePaginator;

    public function getForCms(int $id): ?Safety;

    public function saveFromCms(?Safety $safety, array $data): Safety;

    public function deleteByIds(array $ids): int;

    public function restoreByIds(array $ids): int;

    public function restoreById(int $id): ?Safety;
}
