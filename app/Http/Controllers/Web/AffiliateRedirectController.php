<?php

namespace App\Http\Controllers\Web;

use App\Data\Affiliate\AffiliateClickData;
use App\Enums\AffiliateStatus;
use App\Http\Controllers\Controller;
use App\Repositories\Interfaces\AffiliateClickRepositoryInterface;
use App\Repositories\Interfaces\AffiliateLinkRepositoryInterface;

class AffiliateRedirectController extends Controller
{
    public function __construct(
        protected AffiliateLinkRepositoryInterface $linkRepo,
        protected AffiliateClickRepositoryInterface $clickRepo,
    ) {
    }

    public function show(string $slug)
    {
        $link = $this->linkRepo->findBySlug($slug);
        if (! $link) {
            return redirect('/');
        }

        $destination = $this->safeDestination((string) $link->destination_url);

        $affiliate = $link->affiliate;
        $enabled = (int) getConfigDb('config_affiliate_enabled') === 1;
        if (! $enabled || ! $affiliate || (int) $affiliate->status !== AffiliateStatus::Active->value) {
            return redirect($destination);
        }

        $click = $this->clickRepo->findRecent(
            (int) $affiliate->id,
            (string) session()->getId(),
            (int) getCoreConfig('affiliate.click_throttle_minutes', 30),
            (int) $link->id,
        );

        if (! $click) {
            $data = AffiliateClickData::fromRequest((int) $affiliate->id);
            $data->affiliateLinkId = (int) $link->id;
            $data->subId = $link->sub_id;
            $data->productId = $link->product_id ? (int) $link->product_id : null;
            $data->landingUrl = mb_substr($destination, 0, 512);
            $data->utmSource = 'aff_'.$affiliate->code;
            $data->utmMedium = 'affiliates';
            $data->utmCampaign = 'link_'.$link->slug;
            $click = $this->clickRepo->recordClick($data);
        }

        $params = [
            'aff'          => $affiliate->code,
            'utm_source'   => 'aff_'.$affiliate->code,
            'utm_medium'   => 'affiliates',
            'utm_campaign' => 'link_'.$link->slug,
        ];
        if (filled($link->sub_id)) {
            $params['utm_content'] = $link->sub_id;
        }

        if ($click) {
            putCookie(
                getCoreConfig('affiliate.cookie'),
                (string) $click->click_token,
                (int) getConfigDb('config_affiliate_cookie_days', 30) * 24 * 60,
            );
            $params[getCoreConfig('affiliate.param_click')] = $click->click_token;
        }

        $glue = str_contains($destination, '?') ? '&' : '?';

        return redirect($destination.$glue.http_build_query($params));
    }

    protected function safeDestination(string $url): string
    {
        $url = strtok($url, '#') ?: '';

        if ($url === '') {
            return '/';
        }
        if (str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
            return $url;
        }

        $host = parse_url($url, PHP_URL_HOST);
        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);
        if ($host !== null && $appHost !== null && strcasecmp($host, $appHost) === 0) {
            return $url;
        }

        return '/';
    }
}
