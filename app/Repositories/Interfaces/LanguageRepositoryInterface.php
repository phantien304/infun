<?php

namespace App\Repositories\Interfaces;

use App\Models\Entities\Language;
use App\Repositories\Base\BaseRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface LanguageRepositoryInterface extends BaseRepositoryInterface
{
    public function listAllCached(): Collection;

    // ===================== CMS (admin) =====================

    public function listForCms(Request $request): LengthAwarePaginator;

    public function getForCms(int $id): ?Language;

    public function saveFromCms(?Language $language, array $data): Language;

    /**
     * Trả về danh sách id KHÔNG xoá được (còn là ngôn ngữ duy nhất chưa bị
     * xoá) — mirror mt219 BaseCmsController::_ignoreDelete (chặn xoá hết
     * sạch ngôn ngữ). Controller loại các id này trước khi gọi deleteByIds.
     */
    public function idsBlockedFromDelete(array $ids): array;

    public function deleteByIds(array $ids): int;

    public function restoreByIds(array $ids): int;

    public function restoreById(int $id): ?Language;
}
