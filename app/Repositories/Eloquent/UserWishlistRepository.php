<?php

namespace App\Repositories\Eloquent;

use App\Models\Entities\UserWishlist;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Interfaces\UserWishlistRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class UserWishlistRepository extends QueryableRepository implements UserWishlistRepositoryInterface
{
    public function model(): string
    {
        return UserWishlist::class;
    }

    public function listForUser(int $userId): Collection
    {
        return $this->resetModel()
            ->where('user_id', $userId)
            ->with([
                'product.description',
                'product.productSpecial',
                'product.stockStatus',
            ])
            ->get();
    }

    public function countForUser(int $userId): int
    {
        return (int) $this->resetModel()
            ->where('user_id', $userId)
            ->count();
    }

    public function findForUser(int $userId, int $productId): ?UserWishlist
    {
        return $this->resetModel()
            ->where('user_id', $userId)
            ->where('product_id', $productId)
            ->first();
    }

    public function addForUser(int $userId, int $productId): UserWishlist
    {
        $existing = $this->findForUser($userId, $productId);
        if ($existing) {
            return $existing;
        }

        return $this->resetModel()->newQuery()->create([
            'user_id'    => $userId,
            'product_id' => $productId,
        ]);
    }

    public function deleteForUser(int $userId, int $productId): bool
    {
        return (bool) $this->resetModel()
            ->where('user_id', $userId)
            ->where('product_id', $productId)
            ->delete();
    }
}
