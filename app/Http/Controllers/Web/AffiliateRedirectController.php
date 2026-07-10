<?php

namespace App\Http\Controllers\Web;

use App\Data\Affiliate\AffiliateClickData;
use App\Enums\AffiliateStatus;
use App\Http\Controllers\Controller;
use App\Repositories\Interfaces\AffiliateClickRepositoryInterface;
use App\Repositories\Interfaces\AffiliateLinkRepositoryInterface;
use Illuminate\Support\Facades\Cookie;

/**
 * GET /l/{slug} — short link kiểu s.shopee.vn (AFFILIATE-PLAN.md mục 2.4).
 * Log click SERVER-SIDE tại bước redirect (trước khi landing load, không
 * phụ thuộc JS/cookie phía đích) → set cookie aff_ref = click_token →
 * 302 sang destination kèm auto-UTM:
 *   ?aff=<code>&aff_click=<token>&utm_source=aff_<code>&utm_medium=affiliates
 *   &utm_campaign=link_<slug>&utm_content=<sub_id>
 * Bảo mật: destination validate cùng host lúc redirect (chống open-redirect
 * kể cả khi DB bị sửa tay); slug/link hỏng → về trang chủ, không lộ lỗi.
 */
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
            return redirect($destination); // link vẫn dùng được, chỉ không track
        }

        $click = $this->clickRepo->findRecent(
            (int) $affiliate->id,
            (string) session()->getId(),
            (int) getCoreConfig('affiliate.click_throttle_minutes', 30),
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

        Cookie::queue(
            getCoreConfig('affiliate.cookie'),
            (string) $click->click_token,
            (int) getConfigDb('config_affiliate_cookie_days', 30) * 24 * 60,
        );

        $params = [
            'aff'                                   => $affiliate->code,
            getCoreConfig('affiliate.param_click')  => $click->click_token,
            'utm_source'                            => 'aff_'.$affiliate->code,
            'utm_medium'                            => 'affiliates',
            'utm_campaign'                          => 'link_'.$link->slug,
        ];
        if (filled($link->sub_id)) {
            $params['utm_content'] = $link->sub_id;
        }

        $glue = str_contains($destination, '?') ? '&' : '?';

        return redirect($destination.$glue.http_build_query($params));
    }

    /** Chỉ chấp nhận destination cùng host với app (hoặc path tương đối). */
    protected function safeDestination(string $url): string
    {
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
