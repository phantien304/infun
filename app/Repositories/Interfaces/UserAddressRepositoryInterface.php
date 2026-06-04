<?php

namespace App\Repositories\Interfaces;

use App\Models\Entities\UserAddress;
use App\Repositories\Base\BaseRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

interface UserAddressRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * Danh sách địa chỉ giao hàng của user — eager-load zone/district/ward
     * cùng `description` locale-scoped để blade render khỏi N+1.
     */
    public function listForUser(int $userId): Collection;

    public function findForUser(int $userId, int $addressId): ?UserAddress;

    /**
     * Tạo hoặc update địa chỉ. Khi `is_default=1` tự reset các địa chỉ
     * khác của user về 0 — đảm bảo invariant "1 user có tối đa 1 default".
     * Caller wrap trong transaction nếu cần atomic.
     */
    public function upsertForUser(int $userId, array $data): UserAddress;

    public function deleteForUser(int $userId, int $addressId): bool;
}
