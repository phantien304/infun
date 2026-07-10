<?php

namespace App\Repositories\Eloquent;

use App\Data\Affiliate\AffiliateConversionData;
use App\Enums\AffiliateConversionStatus;
use App\Models\Entities\AffiliateConversion;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Interfaces\AffiliateConversionRepositoryInterface;

class AffiliateConversionRepository extends QueryableRepository implements AffiliateConversionRepositoryInterface
{
    public function model(): string
    {
        return AffiliateConversion::class;
    }

    public function recordConversion(AffiliateConversionData $data): void
    {
        if (! $data->valid()) {
            return;
        }

        $exists = $this->resetModel()->where('order_id', $data->orderId)->exists();
        if ($exists) {
            return;
        }

        AffiliateConversion::create([
            'affiliate_id'    => $data->affiliateId,
            'order_id'        => $data->orderId,
            'click_id'        => $data->clickId,
            'coupon_code'     => $data->couponCode,
            'order_total'     => $data->orderTotal,
            'commission'      => $data->commission,
            'commission_rate' => $data->rate,
            'status'          => AffiliateConversionStatus::Pending->value,
        ]);
    }

    public function approveForOrder(int $orderId): void
    {
        if ($orderId <= 0) {
            return;
        }

        $this->resetModel()
            ->where('order_id', $orderId)
            ->where('status', AffiliateConversionStatus::Pending->value)
            ->update([
                'status'      => AffiliateConversionStatus::Approved->value,
                'approved_at' => now(),
            ]);
    }

    public function rejectForOrder(int $orderId): void
    {
        if ($orderId <= 0) {
            return;
        }

        $this->resetModel()
            ->where('order_id', $orderId)
            ->whereIn('status', [
                AffiliateConversionStatus::Pending->value,
                AffiliateConversionStatus::Approved->value,
            ])
            ->update(['status' => AffiliateConversionStatus::Rejected->value]);
    }
}
