<?php

namespace App\Repositories\Eloquent;

use App\Models\Entities\VoucherRewardGrant;
use App\Models\Entities\VoucherRewardRule;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Concerns\CacheableRepository;
use App\Repositories\Interfaces\VoucherRewardRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class VoucherRewardRepository extends QueryableRepository implements VoucherRewardRepositoryInterface
{
    use CacheableRepository;

    public function model(): string
    {
        return VoucherRewardRule::class;
    }

    public function listRunningRules(): Collection
    {
        return $this->resetModel()
            ->newQuery()
            ->running()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    public function incrementGrantedCount(int $ruleId, int $by = 1): int
    {
        return DB::table('voucher_reward_rule')
            ->where('id', $ruleId)
            ->where(function ($q) use ($by) {
                $q->whereNull('quota_total')
                    ->orWhereRaw('granted_count + ? <= quota_total', [$by]);
            })
            ->whereNull('deleted_at')
            ->increment('granted_count', $by);
    }

    public function decrementGrantedCount(int $ruleId, int $by = 1): void
    {
        DB::table('voucher_reward_rule')
            ->where('id', $ruleId)
            ->where('granted_count', '>=', $by)
            ->whereNull('deleted_at')
            ->decrement('granted_count', $by);
    }

    public function grantExists(int $ruleId, int $orderId): bool
    {
        return VoucherRewardGrant::query()
            ->where('rule_id', $ruleId)
            ->where('order_id', $orderId)
            ->exists();
    }

    public function countGrantsForUser(int $ruleId, ?int $userId, string $email): int
    {
        $email = trim(strtolower($email));

        return VoucherRewardGrant::query()
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

    public function createGrant(array $data): VoucherRewardGrant
    {
        if (isset($data['email'])) {
            $data['email'] = trim(strtolower((string) $data['email']));
        }

        return VoucherRewardGrant::create($data);
    }

    public function grantsForOrder(int $orderId): Collection
    {
        return VoucherRewardGrant::query()
            ->where('order_id', $orderId)
            ->with(['voucher', 'rule'])
            ->get();
    }

    public function flushCache(): void
    {

    }
}
