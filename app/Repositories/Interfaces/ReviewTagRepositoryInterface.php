<?php

namespace App\Repositories\Interfaces;

use App\Models\Entities\ReviewTag;
use App\Repositories\Base\BaseRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

interface ReviewTagRepositoryInterface extends BaseRepositoryInterface
{
    public function idsByCodes(array $codes): Collection;

    public function insertPivots(array $rows): void;

    public function incrementUsage(array $tagIds): void;

    /**
     * Gỡ tag khỏi 1 review (CMS sửa lại nhãn) — đối xứng insertPivots().
     */
    public function removePivots(int $reviewId, array $tagIds): void;

    /**
     * Đối xứng incrementUsage() — CMS gỡ tag khỏi review thì trừ lại đúng
     * usage_count (không cho âm).
     */
    public function decrementUsage(array $tagIds): void;

    // ----- CMS (admin) -----
    public function listForCms(Request $request): LengthAwarePaginator;

    public function getForCms(int $id): ?ReviewTag;

    public function saveFromCms(?ReviewTag $tag, array $data): ReviewTag;

    public function deleteByIds(array $ids): int;

    public function restoreByIds(array $ids): int;

    public function restoreById(int $id): ?ReviewTag;
}
