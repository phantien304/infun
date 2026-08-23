<?php

namespace App\Http\Controllers\Api\Cms\Affiliate;

use App\Http\Controllers\Api\Cms\BaseCmsController;
use App\Repositories\Interfaces\AffiliateConversionRepositoryInterface;
use App\Repositories\Interfaces\AffiliateRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Báo cáo affiliate cho admin: tổng quan theo kỳ + bảng xếp hạng KOL.
 *
 * Khoảng mặc định là 30 ngày gần nhất — cùng cửa sổ với dashboard KOL ở cổng
 * affiliate, để hai bên nhìn cùng một con số khi có tranh luận.
 */
class AffiliateReportController extends BaseCmsController
{
    protected string $permission = 'affiliate-report';

    public function __construct(
        private readonly AffiliateRepositoryInterface $affiliateRepo,
        private readonly AffiliateConversionRepositoryInterface $conversionRepo,
    ) {
    }

    public function overview(Request $request)
    {
        $request->validate([
            'date_from' => 'nullable|date_format:Y-m-d',
            'date_to'   => 'nullable|date_format:Y-m-d',
            'limit'     => 'nullable|integer|min:1|max:50',
        ]);

        $to   = $request->input('date_to') ?: Carbon::today()->toDateString();
        $from = $request->input('date_from') ?: Carbon::today()->subDays(29)->toDateString();
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        $top = $this->conversionRepo->topAffiliates($from, $to, (int) $request->input('limit', 10));
        $affiliates = $this->affiliateRepo->findManyWithUser($top->pluck('affiliate_id')->all());

        return respondSuccess([
            'date_from'         => $from,
            'date_to'           => $to,
            'affiliate_status'  => $this->affiliateRepo->countByStatus(),
            'conversion_status' => $this->conversionRepo->summaryByStatus($from, $to),
            'top_affiliates'    => $top->map(function ($row) use ($affiliates) {
                $affiliate = $affiliates->get((int) $row->affiliate_id);

                return [
                    'affiliate_id'   => (int) $row->affiliate_id,
                    'affiliate_code' => $affiliate?->code,
                    'affiliate_name' => $affiliate?->user?->full_name,
                    'clicks_count'   => (int) ($affiliate?->clicks_count ?? 0),
                    'orders'         => (int) $row->orders,
                    'revenue'        => (int) $row->revenue,
                    'commission'     => (int) $row->commission,
                ];
            })->values(),
        ]);
    }
}
