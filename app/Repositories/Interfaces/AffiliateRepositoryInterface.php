<?php

namespace App\Repositories\Interfaces;

use App\Enums\AffiliateStatus;
use App\Models\Entities\Affiliate;
use App\Repositories\Base\BaseRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

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

    // ----- CMS (admin) -----
    public function listForCms(Request $request): LengthAwarePaginator;

    public function getForCms(int $id): ?Affiliate;

    /** Sửa status / commission_rate riêng / payment_info. KHÔNG đụng `code`. */
    public function updateFromCms(Affiliate $affiliate, array $data): Affiliate;

    /** Gán coupon riêng cho KOL; gỡ coupon đó khỏi KOL khác (1 coupon = 1 KOL). */
    public function syncCoupons(Affiliate $affiliate, array $couponIds): Affiliate;

    public function setStatus(Affiliate $affiliate, AffiliateStatus $status): Affiliate;

    /** Nạp nhiều KOL kèm user theo id, keyBy id — tránh N+1 sau truy vấn gom nhóm. */
    public function findManyWithUser(array $ids): Collection;

    /** [status => count] — thẻ số liệu màn báo cáo. */
    public function countByStatus(): array;
}
