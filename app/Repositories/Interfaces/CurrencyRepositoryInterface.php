<?php

namespace App\Repositories\Interfaces;

use App\Models\Entities\Currency;
use App\Repositories\Base\BaseRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface CurrencyRepositoryInterface extends BaseRepositoryInterface
{
    public function listAllCached(): Collection;

    // ===================== CMS (admin) =====================

    public function listForCms(Request $request): LengthAwarePaginator;

    public function getForCms(int $id): ?Currency;

    public function saveFromCms(?Currency $currency, array $data): Currency;

    /**
     * Mirror LanguageRepository::idsBlockedFromDelete — chặn xoá khi chỉ
     * còn đúng 1 currency active (hệ thống cần ít nhất 1 loại tiền tệ).
     */
    public function idsBlockedFromDelete(array $ids): array;

    public function deleteByIds(array $ids): int;

    public function restoreByIds(array $ids): int;

    public function restoreById(int $id): ?Currency;
}
