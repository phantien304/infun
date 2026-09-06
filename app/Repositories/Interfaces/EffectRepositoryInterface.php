<?php

namespace App\Repositories\Interfaces;

use App\Models\Entities\Effect;
use App\Repositories\Base\BaseRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

interface EffectRepositoryInterface extends BaseRepositoryInterface
{
    public function listForCms(Request $request): LengthAwarePaginator;

    public function getForCms(int $id): ?Effect;

    public function saveFromCms(?Effect $effect, array $data): Effect;

    public function deleteByIds(array $ids): int;

    public function restoreByIds(array $ids): int;

    public function restoreById(int $id): ?Effect;
}
