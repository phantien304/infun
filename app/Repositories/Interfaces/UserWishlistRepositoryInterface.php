<?php

namespace App\Repositories\Interfaces;

use App\Models\Entities\UserWishlist;
use App\Repositories\Base\BaseRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

interface UserWishlistRepositoryInterface extends BaseRepositoryInterface
{
    public function listForUser(int $userId): Collection;

    public function countForUser(int $userId): int;

    public function findForUser(int $userId, int $productId): ?UserWishlist;

    public function addForUser(int $userId, int $productId): UserWishlist;

    public function deleteForUser(int $userId, int $productId): bool;
}
