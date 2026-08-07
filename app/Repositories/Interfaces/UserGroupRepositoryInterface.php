<?php

namespace App\Repositories\Interfaces;

use App\Models\Entities\UserGroup;
use App\Repositories\Base\BaseRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface UserGroupRepositoryInterface extends BaseRepositoryInterface
{
    public function listWithDescription(): Collection;

    // ----- CMS (admin) -----
    public function listForCms(Request $request): LengthAwarePaginator;

    public function getForCms(int $id): ?UserGroup;

    public function saveFromCms(?UserGroup $userGroup, array $data): UserGroup;

    public function deleteByIds(array $ids): int;

    public function restoreByIds(array $ids): int;

    public function restoreById(int $id): ?UserGroup;
}
