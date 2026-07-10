<?php

namespace App\Services\Reward;

use App\Models\Entities\Product;

class RewardEarnService
{
    public function enabled(): bool
    {
        return getConfigDb('config_reward_point_enabled') != setting('reward_point.disable');
    }

    public function perUnit(Product $product, int $price): int
    {
        if (! $this->enabled()) {
            return 0;
        }

        $row = $product->productRewards->firstWhere('user_group_id', (int) getUserGroupId());
        $perUnit = (int) ($row->points ?? 0);
        if ($perUnit > 0) {
            return $perUnit;
        }

        $divisor = (int) getConfigDb('config_reward_earn_divisor');
        if ($divisor > 0) {
            return intdiv(max(0, $price), $divisor);
        }

        return 0;
    }
}
