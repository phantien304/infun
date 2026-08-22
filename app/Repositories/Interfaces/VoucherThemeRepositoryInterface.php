<?php

namespace App\Repositories\Interfaces;

use App\Models\Entities\VoucherTheme;
use App\Repositories\Base\BaseRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

interface VoucherThemeRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * Danh sách theme còn sống + tên theo ngôn ngữ — dùng cho dropdown ở
     * form voucher (CMS) và cho storefront khi khách chọn mẫu thiệp.
     */
    public function listWithDescription(): Collection;

    // ----- CMS (admin) -----
    public function listForCms(Request $request): LengthAwarePaginator;

    public function getForCms(int $id): ?VoucherTheme;

    public function saveFromCms(?VoucherTheme $theme, array $data): VoucherTheme;

    public function deleteByIds(array $ids): int;

    public function restoreByIds(array $ids): int;

    public function restoreById(int $id): ?VoucherTheme;
}
