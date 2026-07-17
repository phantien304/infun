<?php

namespace App\Models\Entities;

use App\Enums\VoucherRewardRuleStatus;
use App\Models\Base\Base;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class VoucherRewardRule extends Base
{
    use SoftDeletes;

    protected $table = 'voucher_reward_rule';
    protected $primaryKeyAutoIncrement = 'id';
    public $incrementing = true;
    public $timestamps = true;
    protected $guarded = [];

    protected $casts = [
        'min_order_total'    => 'float',
        'reward_amount'      => 'float',
        'reward_expire_days' => 'integer',
        'max_per_user'       => 'integer',
        'quota_total'        => 'integer',
        'granted_count'      => 'integer',
        'date_start'         => 'datetime',
        'date_end'           => 'datetime',
        'status'             => 'integer',
        'sort_order'         => 'integer',
    ];

    public function grants(): HasMany
    {
        return $this->hasMany(VoucherRewardGrant::class, 'rule_id', 'id');
    }

    public function scopeRunning(Builder $query, ?Carbon $at = null): Builder
    {
        $at ??= Carbon::now();

        return $query
            ->where("{$this->getTable()}.status", VoucherRewardRuleStatus::Active->value)
            ->where(function (Builder $q) use ($at) {
                $q->whereNull("{$this->getTable()}.date_start")
                    ->orWhere("{$this->getTable()}.date_start", '<=', $at);
            })
            ->where(function (Builder $q) use ($at) {
                $q->whereNull("{$this->getTable()}.date_end")
                    ->orWhere("{$this->getTable()}.date_end", '>=', $at);
            });
    }

    public function quotaRemaining(): ?int
    {
        if ($this->quota_total === null) {
            return null;
        }

        return max(0, (int) $this->quota_total - (int) $this->granted_count);
    }

    public function matchesOrderTotal(float $orderTotal): bool
    {
        return $orderTotal >= (float) $this->min_order_total;
    }
}
