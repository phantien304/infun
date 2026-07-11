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
     *
     * Anti-fraud (Phase 6) nằm TRONG đây để mọi caller được bảo vệ:
     * - Dedupe cùng (affiliate, link, IP, UA) trong `affiliate.dedupe_minutes`
     *   → trả click cũ (chặn bot xóa cookie/session để bơm click).
     * - Vượt `affiliate.max_clicks_per_day` → trả null (bỏ log, vẫn redirect).
     */
    public function recordClick(AffiliateClickData $data): ?AffiliateClick;

    /**
     * Click gần nhất cùng (affiliate, session, link) trong X phút — throttle
     * chống spam log; có thì tái dùng token cũ thay vì insert row mới.
     * $linkId phân biệt theo link (null = click ?ref= trực tiếp) để
     * clicks_count/sub_id per-link không lệch khi KOL có nhiều link.
     */
    public function findRecent(int $affiliateId, string $sessionId, int $minutes, ?int $linkId = null): ?AffiliateClick;

    /**
     * Click theo token, affiliate còn ACTIVE và click chưa quá $maxDays
     * (chặn cookie giả/quá hạn) — nguồn attribution phía checkout.
     */
    public function findValidByToken(string $token, int $maxDays): ?AffiliateClick;

    /** Click gần nhất cùng (affiliate, link, IP, UA) trong X phút — dedupe anti-fraud. */
    public function findRecentByIpUa(int $affiliateId, string $ip, string $userAgent, int $minutes, ?int $linkId = null): ?AffiliateClick;

    /** Số click hôm nay của affiliate — cap max_clicks_per_day. */
    public function countToday(int $affiliateId): int;

    /** Xóa click cũ hơn $days theo chunk (ledger volume lớn) — trả số row đã xóa. */
    public function pruneOlderThan(int $days, int $chunk = 5000): int;

    /** ['Y-m-d' => count] click $days ngày gần nhất — chart dashboard KOL. */
    public function countByDay(int $affiliateId, int $days): array;

    /** [sub_id => count] breakdown kênh của KOL (null gom thành '') — Phase 4. */
    public function countBySubId(int $affiliateId): array;
}
