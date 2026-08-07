<?php

namespace App\Observers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

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
                [
                    $userGroupId, $now, $now,   // MIN
                    $userGroupId, $now, $now,   // MAX
                    $userGroupId, $now, $now,   // CASE > effective
                    $userGroupId, $now, $now,   // CASE - effective
                    $productId,
                ]
            );

            if ($row === null || $row->mn === null) {
                DB::table('product')->where('id', $productId)->update([
                    'min_variant_price'            => null,
                    'max_variant_price'            => null,
                    'max_variant_discount_percent' => null,
                ]);
                return;
            }

            DB::table('product')->where('id', $productId)->update([
                'min_variant_price'            => (float) $row->mn,
                'max_variant_price'            => (float) $row->mx,
                'max_variant_discount_percent' => ((int) $row->pct) > 0 ? (int) $row->pct : null,
            ]);
        } catch (\Throwable $exception) {
            logError('ProductVariantAggregateObserver recompute failed: ' . $exception->getMessage(), [
                'product_id' => $productId,
            ]);
        }
    }
}
