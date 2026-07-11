<?php

namespace App\Repositories\Eloquent;

use App\Models\Entities\AffiliateLink;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Interfaces\AffiliateLinkRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class AffiliateLinkRepository extends QueryableRepository implements AffiliateLinkRepositoryInterface
{
    public function model(): string
    {
        return AffiliateLink::class;
    }

    public function findBySlug(string $slug): ?AffiliateLink
    {
        if ($slug === '') {
            return null;
        }

        return $this->resetModel()->where('slug', $slug)->first();
    }

    public function createLink(int $affiliateId, string $destinationUrl, ?int $productId = null, ?string $subId = null): AffiliateLink
    {
        return AffiliateLink::create([
            'affiliate_id'    => $affiliateId,
            'slug'            => $this->generateUniqueSlug(),
            'destination_url' => $destinationUrl,
            'product_id'      => $productId,
            'sub_id'          => $subId !== null ? Str::limit($subId, 64, '') : null,
        ]);
    }

    public function getListForAffiliate(int $affiliateId, int $perPage = 20): LengthAwarePaginator
    {
        return $this->resetModel()
            ->where('affiliate_id', $affiliateId)
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function countForAffiliate(int $affiliateId): int
    {
        return (int) $this->resetModel()->where('affiliate_id', $affiliateId)->count();
    }

    /** Slug base62 8 ký tự — không đoán được, retry khi trùng. */
    protected function generateUniqueSlug(): string
    {
        do {
            $slug = Str::random(8);
        } while ($this->resetModel()->where('slug', $slug)->exists());

        return $slug;
    }
}
