<?php

namespace App\Repositories\Eloquent;

use App\Enums\VoucherRewardRuleStatus;
use App\Models\Entities\VoucherRewardRule;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Concerns\CacheableRepository;
use App\Repositories\Interfaces\VoucherRewardRuleRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class VoucherRewardRuleRepository extends QueryableRepository implements VoucherRewardRuleRepositoryInterface
{
    use CacheableRepository;

    public function model(): string
    {
        return VoucherRewardRule::class;
    }

    public function listRunningRewardRules(): Collection
    {
        return $this->resetModel()
            ->newQuery()
            ->dateStartToEnd()
            ->where("status", VoucherRewardRuleStatus::Active->value)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    public function incrementRewardRuleCount(int $ruleId, int $by = 1): int
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

    public function decrementRewardRuleCount(int $ruleId, int $by = 1): void
    {
        DB::table('voucher_reward_rule')
            ->where('id', $ruleId)
            ->where('granted_count', '>=', $by)
            ->whereNull('deleted_at')
            ->decrement('granted_count', $by);
    }

    public function flushCache(): void
    {
    }
}
