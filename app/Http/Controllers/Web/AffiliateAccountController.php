<?php

namespace App\Http\Controllers\Web;

use App\Enums\AffiliateStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\AffiliateCreateLinkRequest;
use App\Http\Requests\Web\AffiliateRegisterRequest;
use App\Models\Entities\Affiliate;
use App\Repositories\Interfaces\AffiliateClickRepositoryInterface;
use App\Repositories\Interfaces\AffiliateConversionRepositoryInterface;
use App\Repositories\Interfaces\AffiliateLinkRepositoryInterface;
use App\Services\Affiliate\AffiliatePortalService;

/**
 * Cổng affiliate trong account section (Phase 4 — AFFILIATE-PLAN.md):
 * đăng ký (pending → admin duyệt / auto theo config), dashboard số liệu
 * (stat cards + chart 30 ngày + bảng conversion), link generator kiểu Shopee
 * (short link /l/{slug} + copy + QR + breakdown sub_id) và coupon được cấp.
 * Route nằm trong group auth; CachePage đã except account* nên không lo cache.
 */
class AffiliateAccountController extends Controller
{
    public function __construct(
        protected AffiliatePortalService $portal,
        protected AffiliateConversionRepositoryInterface $conversionRepo,
        protected AffiliateLinkRepositoryInterface $linkRepo,
        protected AffiliateClickRepositoryInterface $clickRepo,
    ) {
        $this->breadcrumbs = [
            ['text' => trans('messages.breadcrumbs.home'), 'href' => '/', 'separator' => false],
            ['text' => trans('messages.breadcrumbs.account'), 'href' => route('account.index'), 'separator' => false],
            ['text' => trans('messages.breadcrumbs.account_affiliate'), 'href' => '', 'separator' => false],
        ];
    }

    /** Trạng thái quyết định view: chưa đăng ký → form; pending/suspended → notice; active → dashboard. */
    public function index()
    {
        $this->processMetaSeo('buildForSeoByConfig', 'account.affiliate.title', 'account.affiliate.description');

        if (! $this->portal->enabled()) {
            return $this->render('web::account.affiliate.disabled');
        }

        $affiliate = $this->portal->findForUser((int) getCurrentUserId());

        if (! $affiliate) {
            return $this->render('web::account.affiliate.register');
        }

        if ((int) $affiliate->status !== AffiliateStatus::Active->value) {
            return $this->render('web::account.affiliate.status', ['affiliate' => $affiliate]);
        }

        return $this->render('web::account.affiliate.dashboard', [
            'affiliate' => $affiliate,
            'stats'     => $this->portal->dashboard($affiliate),
            'entities'  => $this->conversionRepo->getListForAffiliate((int) $affiliate->id),
            'coupons'   => $affiliate->coupons()->get(),
        ]);
    }

    public function register(AffiliateRegisterRequest $request)
    {
        if (! $this->portal->enabled()) {
            return redirect(route('account.affiliate'));
        }

        $affiliate = $this->portal->register((int) getCurrentUserId(), $request->paymentInfo());

        $message = (int) $affiliate->status === AffiliateStatus::Active->value
            ? trans('messages.affiliate.register_active')
            : trans('messages.affiliate.register_pending');

        return redirect(route('account.affiliate'))->with('success', $message);
    }

    public function links()
    {
        $this->processMetaSeo('buildForSeoByConfig', 'account.affiliate.title', 'account.affiliate.description');

        $affiliate = $this->activeAffiliateOrNull();
        if (! $affiliate) {
            return redirect(route('account.affiliate'));
        }

        $links = $this->linkRepo->getListForAffiliate((int) $affiliate->id);

        return $this->render('web::account.affiliate.links', [
            'affiliate'      => $affiliate,
            'entities'       => $links,
            'shortUrls'      => $links->getCollection()
                ->mapWithKeys(fn ($l) => [(int) $l->id => $this->portal->shortUrl($l)]),
            'refUrl'         => url('/') . '?ref=' . $affiliate->code,
            'subIdBreakdown' => $this->clickRepo->countBySubId((int) $affiliate->id),
        ]);
    }

    public function createLink(AffiliateCreateLinkRequest $request)
    {
        $affiliate = $this->activeAffiliateOrNull();
        if (! $affiliate) {
            return redirect(route('account.affiliate'));
        }

        [$link, $error] = $this->portal->createLink(
            $affiliate,
            (string) $request->input('url'),
            $request->input('sub_id'),
        );

        if (! $link) {
            return redirect(route('account.affiliate.links'))->with('failed', $error);
        }

        return redirect(route('account.affiliate.links'))
            ->with('success', trans('messages.affiliate.link_created'))
            ->with('new_link_id', (int) $link->id);
    }

    protected function activeAffiliateOrNull(): ?Affiliate
    {
        if (! $this->portal->enabled()) {
            return null;
        }

        $affiliate = $this->portal->findForUser((int) getCurrentUserId());

        return $affiliate && (int) $affiliate->status === AffiliateStatus::Active->value
            ? $affiliate
            : null;
    }
}
