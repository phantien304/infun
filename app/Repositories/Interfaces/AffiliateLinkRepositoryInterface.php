<?php

namespace App\Repositories\Interfaces;

use App\Models\Entities\AffiliateLink;
use App\Repositories\Base\BaseRepositoryInterface;

interface AffiliateLinkRepositoryInterface extends BaseRepositoryInterface
{
    /** Lookup cho route redirect /l/{slug}. */
    public function findBySlug(string $slug): ?AffiliateLink;

    /** Tạo short link — sinh slug base62 unique. destination PHẢI cùng domain (validate ở service/request). */
    public function createLink(int $affiliateId, string $destinationUrl, ?int $productId = null, ?string $subId = null): AffiliateLink;

    public function getListForAffiliate(int $affiliateId, int $perPage = 20): \Illuminate\Contracts\Pagination\LengthAwarePaginator;

    /** Tổng số link đã tạo của affiliate — cap affiliate.max_links (Phase 6). */
    public function countForAffiliate(int $affiliateId): int;
}
