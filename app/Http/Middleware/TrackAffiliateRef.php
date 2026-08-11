<?php

namespace App\Http\Middleware;

use App\Data\Affiliate\AffiliateClickData;
use App\Repositories\Interfaces\AffiliateClickRepositoryInterface;
use App\Repositories\Interfaces\AffiliateRepositoryInterface;
use Closure;
use Illuminate\Http\Request;

class TrackAffiliateRef
{
    public function __construct(
        protected AffiliateRepositoryInterface $affiliateRepo,
        protected AffiliateClickRepositoryInterface $affiliateClickRepo,
    ) {
    }

    public function handle(Request $request, Closure $next)
    {
        if ($request->isMethod('GET') && (int) getConfigDb('config_affiliate_enabled') === 1) {
            try {
                $this->track($request);
            } catch (\Throwable $e) {
                logError('TrackAffiliateRef: '.$e->getMessage());
            }
        }

        return $next($request);
    }

    protected function track(Request $request): void
    {
        $token = $this->stringQuery($request, (string) getCoreConfig('affiliate.param_click'));
        if ($token !== '') {
            $click = $this->affiliateClickRepo->findValidByToken(
                $token,
                (int) getConfigDb('config_affiliate_cookie_days', 30),
            );
            if ($click) {
                $this->queueCookie((string) $click->click_token);
            }

            return;
        }

        $code = $this->stringQuery($request, (string) getCoreConfig('affiliate.param_ref'));
        if ($code === '' || strlen($code) > 32) {
            return;
        }

        $affiliate = $this->affiliateRepo->findActiveByCode($code);
        if (! $affiliate) {
            return;
        }

        $click = $this->affiliateClickRepo->findRecent(
            (int) $affiliate->id,
            (string) session()->getId(),
            (int) getCoreConfig('affiliate.click_throttle_minutes', 30),
        );

        if (! $click) {
            $click = $this->affiliateClickRepo->recordClick(
                AffiliateClickData::fromRequest((int) $affiliate->id),
            );
        }

        if ($click) {
            $this->queueCookie((string) $click->click_token);
        }
    }

    protected function stringQuery(Request $request, string $key): string
    {
        $value = $request->query($key);

        return is_string($value) ? $value : '';
    }

    protected function queueCookie(string $token): void
    {
        putCookie(
            getCoreConfig('affiliate.cookie'),
            $token,
            (int) getConfigDb('config_affiliate_cookie_days', 30) * 24 * 60,
        );
    }
}
