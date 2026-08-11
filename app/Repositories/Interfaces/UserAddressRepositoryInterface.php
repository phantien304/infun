<?php

namespace App\Repositories\Interfaces;

use App\Models\Entities\UserAddress;
use App\Repositories\Base\BaseRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

interface UserAddressRepositoryInterface extends BaseRepositoryInterface
{
    public function listForUser(int $userId): Collection;

    public function findForUser(int $userId, int $addressId): ?UserAddress;

    public function upsertForUser(int $userId, array $data): UserAddress;

    public function deleteForUser(int $userId, int $addressId): bool;

    public function setDefault(int $userId, int $addressId): bool;
}
