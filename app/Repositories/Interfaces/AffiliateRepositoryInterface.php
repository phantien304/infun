<?php

namespace App\Repositories\Interfaces;

use App\Models\Entities\Affiliate;
use App\Repositories\Base\BaseRepositoryInterface;

interface AffiliateRepositoryInterface extends BaseRepositoryInterface
{
    /** Affiliate ACTIVE theo mã ref — dùng ở middleware tracking. */
    public function findActiveByCode(string $code): ?Affiliate;

    public function findByUserId(int $userId): ?Affiliate;

    /** Affiliate ACTIVE sở hữu coupon (attribution qua mã KOL). */
    public function findActiveByCouponCode(string $couponCode): ?Affiliate;

    /**
     * Tạo hồ sơ affiliate cho user (đăng ký) — sinh code unique, status
     * Pending hoặc Active theo config_affiliate_auto_approve.
     */
    public function register(int $userId, array $paymentInfo = []): Affiliate;
}
