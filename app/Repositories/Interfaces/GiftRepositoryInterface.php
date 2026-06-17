<?php

namespace App\Repositories\Interfaces;

use App\Models\Entities\Gift;
use App\Repositories\Base\BaseRepositoryInterface;
use Illuminate\Support\Collection;

interface GiftRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * Tất cả gift active hiện tại (is_active + window + quota chưa hết).
     * KHÔNG check trigger ở đây — service layer sẽ filter theo cart.
     *
     * @return Collection<int, Gift>
     */
    public function listActive(): Collection;

    public function findActiveById(int $giftId): ?Gift;

    public function flushCache(): void;
}
