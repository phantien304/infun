<?php

namespace App\Repositories\Eloquent;

use App\Models\Entities\VoucherRewardGrant;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Concerns\CacheableRepository;
use App\Repositories\Interfaces\VoucherRewardGrantRepositoryInterface;
use Illuminate\Support\Collection;

class VoucherRewardGrantRepository extends QueryableRepository implements VoucherRewardGrantRepositoryInterface
{
    use CacheableRepository;

    public function model(): string
    {
        return VoucherRewardGrant::class;
    }

    public function rewardGrantExists(int $ruleId, int $orderId): bool
    {
        return $this->resetModel()
            ->newQuery()
            ->where('rule_id', $ruleId)
            ->where('order_id', $orderId)
            ->exists();
    }

    public function countRewardGrantsForUser(int $ruleId, ?int $userId, string $email): int
    {
        $email = trim(strtolower($email));

        return $this->resetModel()
            ->newQuery()
            ->where('rule_id', $ruleId)
            ->where(function ($q) use ($userId, $email) {
                if ($userId) {
                    $q->where('user_id', $userId);
                    if ($email !== '') {
                        $q->orWhere('email', $email);
                    }

                    return;
                }
                $q->where('email', $email);
            })
            ->count();
    }

    public function createRewardGrant(array $data): VoucherRewardGrant
    {
        if (isset($data['email'])) {
            $data['email'] = trim(strtolower((string) $data['email']));
        }

        return VoucherRewardGrant::create($data);
    }

    public function rewardGrantsForOrder(int $orderId): Collection
    {
        return $this->resetModel()
            ->newQuery()
            ->where('order_id', $orderId)
            ->with(['voucher', 'rule'])
            ->get();
    }

    public function flushCache(): void
    {
    }
}
