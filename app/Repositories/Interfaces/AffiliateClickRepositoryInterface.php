<?php

namespace App\Repositories\Interfaces;

use App\Data\Affiliate\AffiliateClickData;
use App\Models\Entities\AffiliateClick;
use App\Repositories\Base\BaseRepositoryInterface;

interface AffiliateClickRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * Ghi click (sinh click_token unique) + tăng aggregate clicks_count
     * trên affiliate và affiliate_link.
     */
    public function recordClick(AffiliateClickData $data): AffiliateClick;

    /**
     * Click gần nhất cùng (affiliate, session) trong X phút — throttle chống
     * spam log; có thì tái dùng token cũ thay vì insert row mới.
     */
    public function findRecent(int $affiliateId, string $sessionId, int $minutes): ?AffiliateClick;

    /**
     * Click theo token, affiliate còn ACTIVE và click chưa quá $maxDays
     * (chặn cookie giả/quá hạn) — nguồn attribution phía checkout.
     */
    public function findValidByToken(string $token, int $maxDays): ?AffiliateClick;
}
