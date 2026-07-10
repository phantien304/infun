<?php

namespace App\Repositories\Eloquent;

use App\Enums\AffiliateStatus;
use App\Models\Entities\Affiliate;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Interfaces\AffiliateRepositoryInterface;
use Illuminate\Support\Str;

class AffiliateRepository extends QueryableRepository implements AffiliateRepositoryInterface
{
    public function model(): string
    {
        return Affiliate::class;
    }

    public function findActiveByCode(string $code): ?Affiliate
    {
        if ($code === '') {
            return null;
        }

        return $this->resetModel()
            ->where('code', $code)
            ->where('status', AffiliateStatus::Active->value)
            ->first();
    }

    public function findByUserId(int $userId): ?Affiliate
    {
        if ($userId <= 0) {
            return null;
        }

        return $this->resetModel()->where('user_id', $userId)->first();
    }

    public function findActiveByCouponCode(string $couponCode): ?Affiliate
    {
        if ($couponCode === '') {
            return null;
        }

        return $this->resetModel()
            ->where('status', AffiliateStatus::Active->value)
            ->whereHas('coupons', fn ($q) => $q->where('code', $couponCode))
            ->first();
    }

    public function register(int $userId, array $paymentInfo = []): Affiliate
    {
        $existing = $this->findByUserId($userId);
        if ($existing) {
            return $existing;
        }

        $auto = (int) getConfigDb('config_affiliate_auto_approve') === 1;

        return Affiliate::create([
            'user_id'      => $userId,
            'code'         => $this->generateUniqueCode(),
            'status'       => ($auto ? AffiliateStatus::Active : AffiliateStatus::Pending)->value,
            'payment_info' => $paymentInfo ?: null,
            'approved_at'  => $auto ? now() : null,
        ]);
    }

    /** Mã ref 8 ký tự alphanumeric lowercase, retry khi trùng (xác suất cực thấp). */
    protected function generateUniqueCode(): string
    {
        do {
            $code = strtolower(Str::random(8));
        } while ($this->resetModel()->where('code', $code)->exists());

        return $code;
    }
}
