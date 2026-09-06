<?php

namespace App\Repositories\Interfaces;

use App\Models\Entities\BlogTag;
use App\Repositories\Base\BaseRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

interface BlogTagRepositoryInterface extends BaseRepositoryInterface
{
    public function listAllCached(): Collection;

    // ===================== CMS (admin) =====================

    public function listForCms(Request $request): LengthAwarePaginator;

    public function getForCms(int $id): ?BlogTag;

    public function saveFromCms(?BlogTag $tag, array $data): BlogTag;

    public function deleteByIds(array $ids): int;

    public function restoreByIds(array $ids): int;

    public function restoreById(int $id): ?BlogTag;
}
