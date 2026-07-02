<?php

namespace App\Observers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Recompute denormalized aggregate trên bảng `product`:
 *   - min_variant_price             : MIN giá hiệu lực qua mọi variant
 *   - max_variant_price             : MAX giá hiệu lực qua mọi variant
 *   - max_variant_discount_percent  : MAX % giảm (regular vs hiệu lực)
 *
 * Giá hiệu lực mỗi variant = COALESCE(active product_variant_special.price,
 * product_variant.price). Default user_group_id = 1 (khớp default convention
 * dự án). Date range theo `dateStartToEnd` scope (date_start <= now <= date_end,
 * NULL = mở 1 đầu).
 *
 * Hook trên CẢ ProductVariant lẫn ProductVariantSpecial vì cả 2 đều ảnh
 * hưởng aggregate. Cùng tồn tại với `CacheFlushObserver` đã đăng ký qua
 * `$cacheMap` — Laravel hỗ trợ nhiều observer / model.
 *
 * Drift: aggregate KHÔNG tự re-compute khi campaign EXPIRE qua thời gian
 * (date_end < now). Cần cron job daily (TODO) hoặc tự re-trigger bằng
 * `touch()` row product_variant_special khi schedule kết thúc.
 */
class ProductVariantAggregateObserver
{
    public function saved(Model $model): void
    {
        $this->recompute($this->extractProductId($model));
    }

    public function deleted(Model $model): void
    {
        $this->recompute($this->extractProductId($model));
    }

    public function restored(Model $model): void
    {
        $this->recompute($this->extractProductId($model));
    }

    public function forceDeleted(Model $model): void
    {
        $this->recompute($this->extractProductId($model));
    }

    private function extractProductId(Model $model): ?int
    {
        // ProductVariant + ProductVariantSpecial đều có cột `product_id`
        // (Special đã denormalize per CLAUDE.md để tránh JOIN khi backfill).
        $productId = (int) ($model->getAttribute('product_id') ?? 0);
        return $productId > 0 ? $productId : null;
    }

    private function recompute(?int $productId): void
    {
        if (! $productId) {
            return;
        }

        try {
            $userGroupId = (int) (getCoreConfig('user.default_group_id') ?? 1);
            $now = Carbon::now()->toDateTimeString();

            // Effective price subquery dùng chung cho cả min/max/discount.
            // COALESCE(active variant_special, variant.price) — giống logic
            // `Product::effectivePriceExpression` nhánh variant nhưng hot
            // path chỉ cho 1 product nên inline thay vì gọi method static.
            $effectiveSub = '
                COALESCE(
                    (SELECT pvs.price FROM product_variant_special pvs
                     WHERE pvs.product_variant_id = pv.id
                       AND pvs.user_group_id = ?
                       AND (pvs.date_start IS NULL OR pvs.date_start <= ?)
                       AND (pvs.date_end   IS NULL OR pvs.date_end   >= ?)
                       AND pvs.deleted_at IS NULL
                     ORDER BY pvs.priority DESC LIMIT 1),
                    pv.price
                )
            ';

            // 1 query duy nhất: lấy MIN, MAX effective, MAX discount %.
            $row = DB::selectOne(
                "
                SELECT
                    MIN({$effectiveSub}) AS mn,
                    MAX({$effectiveSub}) AS mx,
                    MAX(
                        CASE
                            WHEN pv.regular_price IS NOT NULL
                             AND pv.regular_price > {$effectiveSub}
                            THEN FLOOR((pv.regular_price - {$effectiveSub}) / pv.regular_price * 100)
                            ELSE 0
                        END
                    ) AS pct
                FROM product_variant pv
                WHERE pv.product_id = ?
                  AND pv.deleted_at IS NULL
                ",
                // 12 placeholder: 4 lần `?, ?, ?` cho effectiveSub (3 chỗ
                // dùng) + 1 productId cuối.
                [
                    $userGroupId, $now, $now,   // MIN
                    $userGroupId, $now, $now,   // MAX
                    $userGroupId, $now, $now,   // CASE > effective
                    $userGroupId, $now, $now,   // CASE - effective
                    $productId,
                ]
            );

            if ($row === null || $row->mn === null) {
                // Không còn variant nào — reset về null + has_variants=0.
                DB::table('product')->where('id', $productId)->update([
                    'has_variants'                 => 0,
                    'min_variant_price'            => null,
                    'max_variant_price'            => null,
                    'max_variant_discount_percent' => null,
                ]);
                return;
            }

            DB::table('product')->where('id', $productId)->update([
                'has_variants'                 => 1,
                'min_variant_price'            => (float) $row->mn,
                'max_variant_price'            => (float) $row->mx,
                'max_variant_discount_percent' => ((int) $row->pct) > 0 ? (int) $row->pct : null,
            ]);
        } catch (\Throwable $exception) {
            // Aggregate fail KHÔNG được phá save flow (giống pattern
            // CacheFlushObserver). Card hơi lệch còn hơn là 500 khi
            // admin lưu variant.
            logError('ProductVariantAggregateObserver recompute failed: ' . $exception->getMessage(), [
                'product_id' => $productId,
            ]);
        }
    }
}
