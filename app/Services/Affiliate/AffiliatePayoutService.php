<?php

namespace App\Services\Affiliate;

use App\Enums\AffiliatePayoutStatus;
use App\Models\Entities\AffiliatePayout;
use App\Repositories\Interfaces\AffiliateConversionRepositoryInterface;
use App\Repositories\Interfaces\AffiliatePayoutRepositoryInterface;
use App\Repositories\Interfaces\AffiliateRepositoryInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Chốt kỳ chi trả hoa hồng — Phase 5 AFFILIATE-PLAN.md.
 *
 * Luồng: conversion Approved (đơn đã giao) + đã qua `config_affiliate_hold_days`
 * kể từ `approved_at` → gom theo KOL → KOL nào đạt `config_affiliate_min_payout`
 * thì tạo `affiliate_payout` và đẩy conversion sang Paid.
 *
 * Vì sao hold_days tính từ `approved_at` chứ không phải ngày đặt: đó là mốc
 * đơn giao thành công, và cửa sổ đổi trả bắt đầu từ đó. Trả tiền trước khi
 * hết hạn đổi trả là trả cho đơn có thể bị hủy — tiền đã chuyển thì không
 * đòi lại được, khác hẳn việc lật một dòng status trong DB.
 */
class AffiliatePayoutService
{
    public function __construct(
        protected AffiliateRepositoryInterface $affiliateRepo,
        protected AffiliateConversionRepositoryInterface $conversionRepo,
        protected AffiliatePayoutRepositoryInterface $payoutRepo,
    ) {
    }

    /** Kỳ mặc định = tháng hiện tại, dạng 'YYYY-MM'. */
    public function currentPeriod(): string
    {
        return Carbon::now()->format('Y-m');
    }

    public function holdDays(): int
    {
        return max(0, (int) getConfigDb('config_affiliate_hold_days'));
    }

    public function minPayout(): int
    {
        return max(0, (int) getConfigDb('config_affiliate_min_payout'));
    }

    /**
     * Xem trước kỳ chốt: ai đủ điều kiện, ai chưa đạt ngưỡng — KHÔNG ghi gì.
     *
     * Có bước này vì chốt kỳ là thao tác một chiều về mặt vận hành (kế toán
     * nhìn danh sách rồi chuyển khoản). Bấm nhầm rồi mới xem là quá muộn.
     */
    public function preview(?string $period = null): array
    {
        $period = $this->normalizePeriod($period);
        $cutoff = $this->cutoff();
        $min    = $this->minPayout();

        $rows = $this->conversionRepo->payableGroupedByAffiliate($cutoff);
        $affiliates = $this->affiliateRepo->findManyWithUser($rows->pluck('affiliate_id')->all());

        $eligible = [];
        $below    = [];

        foreach ($rows as $row) {
            $affiliate = $affiliates->get((int) $row->affiliate_id);
            $item = [
                'affiliate_id'   => (int) $row->affiliate_id,
                'affiliate_code' => $affiliate?->code,
                'affiliate_name' => $affiliate?->user?->full_name,
                'conversions'    => (int) $row->cnt,
                'amount'         => (int) $row->total,
            ];

            if ((int) $row->total >= $min) {
                $eligible[] = $item;
            } else {
                $below[] = $item;
            }
        }

        return [
            'period'        => $period,
            'hold_days'     => $this->holdDays(),
            'cutoff'        => $cutoff,
            'min_payout'    => $min,
            'eligible'      => $eligible,
            'below_minimum' => $below,
            'total_amount'  => array_sum(array_column($eligible, 'amount')),
        ];
    }

    /**
     * Chốt kỳ thật. Mỗi KOL một transaction riêng: một KOL lỗi (kỳ đã Paid,
     * đua conversion) không kéo theo cả đợt chốt phải làm lại.
     *
     * @return array{period: string, created: array, skipped: array}
     */
    public function closePeriod(?string $period = null): array
    {
        $period = $this->normalizePeriod($period);
        $cutoff = $this->cutoff();
        $min    = $this->minPayout();

        $created = [];
        $skipped = [];

        foreach ($this->conversionRepo->payableGroupedByAffiliate($cutoff) as $row) {
            $affiliateId = (int) $row->affiliate_id;

            if ((int) $row->total < $min) {
                $skipped[] = ['affiliate_id' => $affiliateId, 'reason' => 'below_minimum', 'amount' => (int) $row->total];
                continue;
            }

            $existing = $this->payoutRepo->findByAffiliateAndPeriod($affiliateId, $period);
            if ($existing && (int) $existing->status !== AffiliatePayoutStatus::Pending->value) {
                // Kỳ đã trả hoặc đã hủy: KHÔNG nhét thêm tiền vào. Phần hoa
                // hồng này để dành cho kỳ sau — đúng hơn là sửa một biên bản
                // kế toán đã chốt.
                $skipped[] = [
                    'affiliate_id' => $affiliateId,
                    'reason'       => 'period_locked',
                    'payout_id'    => (int) $existing->id,
                ];
                continue;
            }

            $payout = DB::transaction(function () use ($affiliateId, $period, $existing, $cutoff) {
                $payout = $existing ?? $this->payoutRepo->createPayout($affiliateId, $period, 0);

                $ids = $this->conversionRepo->payableIdsForAffiliate($affiliateId, $cutoff);
                $this->conversionRepo->attachPayout($ids, (int) $payout->id);

                // Đọc lại tổng THỰC TẾ đã gắn — xem sumCommissionForPayout().
                $payout->amount = $this->conversionRepo->sumCommissionForPayout((int) $payout->id);
                $payout->save();

                return $payout;
            });

            if ((int) $payout->amount <= 0) {
                // Không gắn được dòng nào (đua với observer reject) → kỳ rỗng,
                // hủy luôn cho khỏi rác danh sách.
                $this->payoutRepo->cancel($payout, 'empty_after_close');
                $skipped[] = ['affiliate_id' => $affiliateId, 'reason' => 'nothing_attached'];
                continue;
            }

            $created[] = [
                'payout_id'    => (int) $payout->id,
                'affiliate_id' => $affiliateId,
                'amount'       => (int) $payout->amount,
            ];
        }

        return ['period' => $period, 'created' => $created, 'skipped' => $skipped];
    }

    /**
     * Huỷ kỳ: conversion quay lại Approved để vào kỳ sau. Chỉ huỷ được kỳ
     * còn Pending — kỳ đã Paid nghĩa là tiền đã rời tài khoản, huỷ ở CMS
     * không gọi tiền về được, để đó còn hơn tạo ra sổ sách nói dối.
     */
    public function cancelPayout(AffiliatePayout $payout, ?string $note): array
    {
        if ((int) $payout->status !== AffiliatePayoutStatus::Pending->value) {
            return [null, trans('messages.cms.affiliate.payout_not_pending')];
        }

        $payout = DB::transaction(function () use ($payout, $note) {
            $this->conversionRepo->revertPayout((int) $payout->id);

            return $this->payoutRepo->cancel($payout, $note);
        });

        return [$payout, null];
    }

    /**
     * Dữ liệu file CSV chuyển khoản: mỗi dòng một KOL với thông tin ngân
     * hàng lấy từ `payment_info`.
     *
     * @return array{0: array<int, string>, 1: array<int, array<int, string>>}
     */
    public function exportRows(string $period): array
    {
        $header = [
            'payout_id', 'period', 'affiliate_code', 'affiliate_name',
            'bank_name', 'bank_account', 'bank_holder', 'zalopay_phone',
            'amount', 'status',
        ];

        $payouts = $this->payoutRepo->listByPeriod($period);

        $rows = [];
        foreach ($payouts as $payout) {
            $info = $payout->affiliate?->payment_info;
            $info = is_array($info) ? $info : [];

            $rows[] = [
                (string) $payout->id,
                (string) $payout->period,
                (string) ($payout->affiliate?->code ?? ''),
                (string) ($payout->affiliate?->user?->full_name ?? ''),
                (string) ($info['bank_name'] ?? ''),
                // Ép chuỗi bằng tiền tố ' cho Excel: số tài khoản dài bị Excel
                // tự đổi sang ký hiệu khoa học (1.23457E+12) là mất số thật.
                $this->excelText((string) ($info['bank_account'] ?? '')),
                (string) ($info['bank_holder'] ?? ''),
                $this->excelText((string) ($info['zalopay_phone'] ?? '')),
                (string) (int) $payout->amount,
                (string) (int) $payout->status,
            ];
        }

        return [$header, $rows];
    }

    private function excelText(string $value): string
    {
        return $value === '' ? '' : "'" . $value;
    }

    /** Mốc thời gian: conversion approved trước mốc này mới đủ hold. */
    private function cutoff(): string
    {
        return Carbon::now()->subDays($this->holdDays())->toDateTimeString();
    }

    private function normalizePeriod(?string $period): string
    {
        $period = trim((string) $period);

        return preg_match('/^\d{4}-\d{2}$/', $period) === 1 ? $period : $this->currentPeriod();
    }
}
