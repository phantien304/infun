<?php

namespace App\Repositories\Interfaces;

use App\Models\Entities\Voucher;
use App\Repositories\Base\BaseRepositoryInterface;
use Illuminate\Support\Collection;

interface VoucherRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * [Legacy] Resolve voucher code thành mảng — giữ backward compat cho
     * caller cũ. Code mới gọi `findByCode` + `VoucherService::validate`.
     */
    public function resolveVoucher(?string $code): array;

    public function findByCode(string $code): ?Voucher;

    /**
     * Batch lookup nhiều code trong 1 query (tránh N+1 ở resolveApplied).
     * Trả Collection keyBy 'code' để caller `->get($code)`.
     *
     * @param  array<int, string>  $codes
     * @return Collection<string, Voucher>
     */
    public function findByCodes(array $codes): Collection;

    /**
     * Voucher gắn cho user (gửi tới email) — Shopee "Voucher của tôi" tab.
     * Trả CẢ expired/fully_used để hiển thị state, service filter khi áp.
     *
     * @return Collection<int, Voucher>
     */
    public function listForEmail(string $email): Collection;

    public function flushCache(): void;
}
