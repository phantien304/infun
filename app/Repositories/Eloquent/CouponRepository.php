<?php

namespace App\Repositories\Eloquent;

use App\Models\Entities\Coupon;
use App\Models\Entities\ProductCategory;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Interfaces\CouponRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CouponRepository extends QueryableRepository implements CouponRepositoryInterface
{
    public function model(): string
    {
        return Coupon::class;
    }

    /**
     * Resolve coupon — port nguyên logic từ trait CheckoutMarketing::getCoupon
     * nhưng tách ra repo. Logic giữ nguyên semantics cũ:
     *
     *  - Coupon phải còn trong khoảng [date_start, date_end] (NULL = không
     *    giới hạn đầu đó).
     *  - `total` là min subtotal; nếu subtotal >= total → KHÔNG hợp lệ (semantics
     *    legacy ngược trực giác nhưng giữ nguyên để không vỡ data cũ).
     *  - `uses_total > 0` → đếm CouponHistory.count() so với uses_total.
     *  - Nếu coupon có gắn product/category → cart phải chứa ít nhất 1 sản phẩm
     *    match (qua product_id trực tiếp hoặc qua category).
     *
     * Trả array với cùng keys như legacy để service tính tiền không phải đổi.
     */
    public function resolveCoupon(?string $code, array $cartItems, int $cartSubtotal): array
    {
        if (! filled($code)) {
            return [];
        }

        $now = Carbon::now();

        $coupon = $this->resetModel()
            ->where('code', $code)
            ->where(function ($q) use ($now) {
                $q->where('date_start', '<', $now)->orWhereNull('date_start');
            })
            ->where(function ($q) use ($now) {
                $q->where('date_end', '>', $now)->orWhereNull('date_end');
            })
            ->first();

        if (! $coupon) {
            return [];
        }

        // Legacy semantics: subtotal >= coupon.total (min order) thì FAIL.
        // Giữ nguyên không sửa để dữ liệu coupon cũ vẫn hoạt động như đang chạy.
        if ($coupon->total >= $cartSubtotal) {
            return [];
        }

        $usedCount = (int) DB::table('coupon_history')
            ->where('coupon_id', $coupon->id)
            ->count();
        if ($coupon->uses_total > 0 && $usedCount >= $coupon->uses_total) {
            return [];
        }

        $couponProductIds = $coupon->couponProducts()->pluck('product_id')->all();
        $couponCategoryIds = $coupon->couponCategories()->pluck('category_id')->all();

        $matchedProductIds = [];
        if (! empty($couponProductIds) || ! empty($couponCategoryIds)) {
            foreach ($cartItems as $item) {
                $pid = (int) ($item['id'] ?? 0);
                if (in_array($pid, $couponProductIds, true)) {
                    $matchedProductIds[] = $pid;
                    continue;
                }
                if (! empty($couponCategoryIds) && $this->productInCategories($pid, $couponCategoryIds)) {
                    $matchedProductIds[] = $pid;
                }
            }
            if (empty($matchedProductIds)) {
                return [];
            }
        }

        return [
            'coupon_id'     => $coupon->id,
            'code'          => $coupon->code,
            'name'          => $coupon->name,
            'type'          => $coupon->type,
            'discount'      => $coupon->discount,
            'shipping'      => $coupon->shipping,
            'total'         => $coupon->total,
            'product'       => $matchedProductIds,
            'date_start'    => $coupon->date_start,
            'date_end'      => $coupon->date_end,
            'uses_total'    => $coupon->uses_total,
            'uses_customer' => $coupon->uses_customer,
            'status'        => $coupon->status,
        ];
    }

    protected function productInCategories(int $productId, array $categoryIds): bool
    {
        return ProductCategory::where('product_id', $productId)
            ->whereIn('category_id', $categoryIds)
            ->exists();
    }
}
