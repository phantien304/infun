<?php

namespace App\Repositories\Eloquent;

use App\Data\Affiliate\AffiliateClickData;
use App\Enums\AffiliateStatus;
use App\Models\Entities\Affiliate;
use App\Models\Entities\AffiliateClick;
use App\Models\Entities\AffiliateLink;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Interfaces\AffiliateClickRepositoryInterface;
use Illuminate\Support\Str;

class AffiliateClickRepository extends QueryableRepository implements AffiliateClickRepositoryInterface
{
    public function model(): string
    {
        return AffiliateClick::class;
    }

    public function recordClick(AffiliateClickData $data): AffiliateClick
    {
        $click = $this->resetModel()->create([
            'affiliate_id'      => $data->affiliateId,
            'affiliate_link_id' => $data->affiliateLinkId,
            'click_token'       => $this->generateUniqueToken(),
            'sub_id'            => $data->subId,
            'session_id'        => $data->sessionId,
            'ip'                => $data->ip,
            'user_agent'        => $data->userAgent,
            'landing_url'       => $data->landingUrl,
            'referrer'          => $data->referrer,
            'utm_source'        => $data->utmSource,
            'utm_medium'        => $data->utmMedium,
            'utm_campaign'      => $data->utmCampaign,
            'product_id'        => $data->productId,
            'created_at'        => now(),
        ]);

        Affiliate::where('id', $data->affiliateId)->increment('clicks_count');
        if ($data->affiliateLinkId) {
            AffiliateLink::where('id', $data->affiliateLinkId)->increment('clicks_count');
        }

        return $click;
    }

    public function findRecent(int $affiliateId, string $sessionId, int $minutes): ?AffiliateClick
    {
        if ($affiliateId <= 0 || $sessionId === '' || $minutes <= 0) {
            return null;
        }

        return $this->resetModel()
            ->where('affiliate_id', $affiliateId)
            ->where('session_id', $sessionId)
            ->where('created_at', '>', now()->subMinutes($minutes))
            ->orderByDesc('id')
            ->first();
    }

    public function findValidByToken(string $token, int $maxDays): ?AffiliateClick
    {
        if ($token === '' || strlen($token) > 16) {
            return null;
        }

        return $this->resetModel()
            ->where('click_token', $token)
            ->where('created_at', '>', now()->subDays(max(1, $maxDays)))
            ->whereHas('affiliate', fn ($q) => $q->where('status', AffiliateStatus::Active->value))
            ->with('affiliate')
            ->first();
    }

    /** Token 12 ký tự base62 (cột 16) — unique, retry khi trùng. */
    protected function generateUniqueToken(): string
    {
        do {
            $token = Str::random(12);
        } while ($this->resetModel()->where('click_token', $token)->exists());

        return $token;
    }
}
