<?php

namespace App\Repositories\Interfaces;

use App\Models\Entities\Information;
use App\Repositories\Base\BaseRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

interface InformationRepositoryInterface extends BaseRepositoryInterface
{
    public function getDetail($id): ?Information;

    public function listWithDescription(): Collection;

    // ----- CMS (admin) -----
    public function listForCms(Request $request): LengthAwarePaginator;

    public function getForCms(int $id): ?Information;

    public function saveFromCms(?Information $information, array $data): Information;

    public function deleteByIds(array $ids): int;

    public function restoreByIds(array $ids): int;

    public function restoreById(int $id): ?Information;
}
