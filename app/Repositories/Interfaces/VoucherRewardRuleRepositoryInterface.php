<?php

namespace App\Repositories\Interfaces;

use App\Repositories\Base\BaseRepositoryInterface;
use Illuminate\Support\Collection;

interface VoucherRewardRuleRepositoryInterface extends BaseRepositoryInterface
{
    public function listRunningRewardRules(): Collection;

    public function incrementRewardRuleCount(int $ruleId, int $by = 1): int;

    public function decrementRewardRuleCount(int $ruleId, int $by = 1): void;

    public function flushCache(): void;
}
