<?php

namespace App\Services\Account;

use App\Repositories\Interfaces\UserWishlistRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

/**
 * Wishlist CRUD + đồng bộ session counter cho header.
 *
 * `session.total_wishlist` ở header menu phụ thuộc count → mỗi mutate
 * (toggle/remove) phải gọi `syncSessionCounter()` để badge không drift.
 */
class WishlistService
{
    public function __construct(
        protected UserWishlistRepositoryInterface $wishlistRepo,
    ) {
    }

    public function listForUser(int $userId): Collection
    {
        $items = $this->wishlistRepo->listForUser($userId);
        $this->syncSessionCounter($items->count());

        return $items;
    }

    /**
     * Toggle: nếu đã có → xoá, chưa có → thêm. Trả `true` nếu state mới là
     * "đã thêm", `false` nếu state mới là "đã xoá" (semantic ngược với
     * 'delete' của response legacy, tiện cho controller).
     */
    public function toggle(int $userId, int $productId): bool
    {
        $existing = $this->wishlistRepo->findForUser($userId, $productId);
        if ($existing) {
            $this->wishlistRepo->deleteForUser($userId, $productId);
            $added = false;
        } else {
            $this->wishlistRepo->addForUser($userId, $productId);
            $added = true;
        }
        $this->syncSessionCounter();

        return $added;
    }

    public function remove(int $userId, int $productId): bool
    {
        $ok = $this->wishlistRepo->deleteForUser($userId, $productId);
        $this->syncSessionCounter();

        return $ok;
    }

    public function countForUser(int $userId): int
    {
        return $this->wishlistRepo->countForUser($userId);
    }

    protected function syncSessionCounter(?int $count = null): void
    {
        $userId = (int) (getCurrentUserId() ?? 0);
        if ($userId <= 0) {
            return;
        }
        $count ??= $this->wishlistRepo->countForUser($userId);
        $key = (string) getCoreConfig('session.total_wishlist');
        if (session()->get($key) !== $count) {
            session()->put($key, $count);
        }
    }
}
