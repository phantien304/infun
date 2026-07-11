<?php

namespace App\Repositories\Eloquent;

use App\Data\Affiliate\AffiliateClickData;
use App\Enums\AffiliateStatus;
use App\Models\Entities\Affiliate;
use App\Models\Entities\AffiliateClick;
use App\Models\Entities\AffiliateLink;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Interfaces\AffiliateClickRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AffiliateClickRepository extends QueryableRepository implements AffiliateClickRepositoryInterface
{
    public function model(): string
    {
        return AffiliateClick::class;
    }

    public function recordClick(AffiliateClickData $data): ?AffiliateClick
    {
        // Anti-fraud 1: dedupe theo IP+UA (không phụ thuộc session/cookie).
        $dedupeMinutes = (int) getCoreConfig('affiliate.dedupe_minutes', 10);
        if ($dedupeMinutes > 0 && filled($data->ip)) {
            $existing = $this->findRecentByIpUa(
                $data->affiliateId,
                (string) $data->ip,
                (string) $data->userAgent,
                $dedupeMinutes,
                $data->affiliateLinkId,
            );
            if ($existing) {
                return $existing;
            }
        }

        // Anti-fraud 2: cap click/ngày/affiliate (0 = không cap).
        $cap = (int) getCoreConfig('affiliate.max_clicks_per_day', 0);
        if ($cap > 0 && $this->countToday($data->affiliateId) >= $cap) {
            return null;
        }

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

    public function findRecent(int $affiliateId, string $sessionId, int $minutes, ?int $linkId = null): ?AffiliateClick
    {
        if ($affiliateId <= 0 || $sessionId === '' || $minutes <= 0) {
            return null;
        }

        return $this->resetModel()
            ->where('affiliate_id', $affiliateId)
            ->where('session_id', $sessionId)
            ->when(
                $linkId !== null,
                fn ($q) => $q->where('affiliate_link_id', $linkId),
                fn ($q) => $q->whereNull('affiliate_link_id'),
            )
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

    public function findRecentByIpUa(int $affiliateId, string $ip, string $userAgent, int $minutes, ?int $linkId = null): ?AffiliateClick
    {
        if ($affiliateId <= 0 || $ip === '' || $minutes <= 0) {
            return null;
        }

        return $this->resetModel()
            ->where('affiliate_id', $affiliateId)
            ->where('ip', $ip)
            ->where('user_agent', mb_substr($userAgent, 0, 255))
            ->when(
                $linkId !== null,
                fn ($q) => $q->where('affiliate_link_id', $linkId),
                fn ($q) => $q->whereNull('affiliate_link_id'),
            )
            ->where('created_at', '>', now()->subMinutes($minutes))
            ->orderByDesc('id')
            ->first();
    }

    public function countToday(int $affiliateId): int
    {
        return (int) $this->resetModel()
            ->where('affiliate_id', $affiliateId)
            ->where('created_at', '>=', now()->startOfDay())
            ->count();
    }

    public function pruneOlderThan(int $days, int $chunk = 5000): int
    {
        $cutoff = now()->subDays(max(1, $days));
        $total = 0;

        do {
            $deleted = $this->resetModel()
                ->where('created_at', '<', $cutoff)
                ->limit(max(100, $chunk))
                ->delete();
            $total += $deleted;
        } while ($deleted > 0);

        return $total;
    }

    public function countByDay(int $affiliateId, int $days): array
    {
        $rows = $this->resetModel()
            ->where('affiliate_id', $affiliateId)
            ->where('created_at', '>=', now()->subDays(max(1, $days))->startOfDay())
            ->groupBy('d')
            ->orderBy('d')
            ->get([DB::raw('DATE(created_at) as d'), DB::raw('COUNT(*) as cnt')]);

        return $rows->pluck('cnt', 'd')->map(fn ($v) => (int) $v)->all();
    }

    public function countBySubId(int $affiliateId): array
    {
        $rows = $this->resetModel()
            ->where('affiliate_id', $affiliateId)
            ->groupBy('sub_id')
            ->orderByDesc('cnt')
            ->limit(20)
            ->get(['sub_id', DB::raw('COUNT(*) as cnt')]);

        $map = [];
        foreach ($rows as $row) {
            $map[(string) $row->sub_id] = (int) $row->cnt;
        }

        return $map;
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
