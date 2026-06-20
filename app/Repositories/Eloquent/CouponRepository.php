<?php

namespace App\Repositories\Eloquent;

use App\Models\Entities\Coupon;
use App\Models\Entities\CouponHistory;
use App\Models\Entities\ProductCategory;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Concerns\CacheableRepository;
use App\Repositories\Interfaces\CouponRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CouponRepository extends QueryableRepository implements CouponRepositoryInterface
{
    use CacheableRepository;

    public function model(): string
    {
        return Coupon::class;
    }

    public function listActiveForUser(?int $userGroupId): Collection
    {
        return $this->rememberCache(
            $this->cacheKeyActive($userGroupId),
            fn () => $this->resetModel()
                ->newQuery()
                ->active()
                ->forUserGroupOrPublic($userGroupId)
                ->with(['couponProducts', 'couponCategories'])
                ->orderByDesc('sort_order')
                ->orderBy('id')
                ->get(),
            getCoreConfig('time.cache'),
            tags: [getCoreConfig('coupon.cache.tag_root')],
        );
    }

    public function listSavedByUser(int $userId): Collection
    {
        return $this->resetModel()
            ->newQuery()
            ->savedBy($userId)
            ->with(['couponProducts', 'couponCategories'])
            ->orderByDesc('sort_order')
            ->orderBy('id')
            ->get();
    }

    public function findByCode(string $code): ?Coupon
    {
        $code = trim($code);
        if ($code === '') {
            return null;
        }

        return $this->resetModel()
            ->newQuery()
            ->where('code', $code)
            ->with(['couponProducts', 'couponCategories'])
            ->first();
    }

    public function countUsedByUser(int $userId, int $couponId): int
    {
        return CouponHistory::query()
            ->forUser($userId)
            ->forCoupon($couponId)
            ->usedOrApplied()
            ->count();
    }

    public function countUsedByUserForCoupons(int $userId, array $couponIds): array
    {
        if (empty($couponIds)) {
            return [];
        }

        return CouponHistory::query()
            ->forUser($userId)
            ->whereIn('coupon_id', $couponIds)
            ->usedOrApplied()
            ->selectRaw('coupon_id, COUNT(*) as aggregate')
            ->groupBy('coupon_id')
            ->pluck('aggregate', 'coupon_id')
            ->map(fn ($count) => (int) $count)
            ->all();
    }

    public function flushCache(): void
    {
        $this->forgetCacheTagged([getCoreConfig('coupon.cache.tag_root')]);
    }

    private function cacheKeyActive(?int $userGroupId): string
    {
        return getCoreConfig('coupon.cache.key_active') . ':' . ($userGroupId ?? 'public');
    }

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
            'status'        => $coupon->status ?? null,
        ];
    }

    protected function productInCategories(int $productId, array $categoryIds): bool
    {
        return ProductCategory::where('product_id', $productId)
            ->whereIn('category_id', $categoryIds)
            ->exists();
    }
}
