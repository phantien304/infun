<?php

namespace App\Repositories\Interfaces;

use App\Models\Entities\Zone;
use App\Repositories\Base\BaseRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface ZoneRepositoryInterface extends BaseRepositoryInterface
{
    public function nameById(int $id): string;

    public function listAllCached(): Collection;

    // ===================== CMS (admin) =====================

    public function listForCms(Request $request): LengthAwarePaginator;

    public function getForCms(int $id): ?Zone;

    public function saveFromCms(?Zone $zone, array $data): Zone;

    public function deleteByIds(array $ids): int;

    public function restoreByIds(array $ids): int;

    public function restoreById(int $id): ?Zone;
}
