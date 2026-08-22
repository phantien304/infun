<?php

namespace App\Repositories\Eloquent;

use App\Models\Entities\Coupon;
use App\Models\Entities\ProductCategory;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Concerns\CacheableRepository;
use App\Repositories\Interfaces\CouponRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
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

    public function existsById(int $couponId): bool
    {
        return $this->resetModel()->newQuery()->where('id', $couponId)->exists();
    }

    public function isActiveForUserGroup(int $couponId, ?int $userGroupId): bool
    {
        return $this->resetModel()
            ->newQuery()
            ->where('id', $couponId)
            ->active()
            ->forUserGroupOrPublic($userGroupId)
            ->exists();
    }

    public function incrementUsedCount(int $couponId, int $by = 1): int
    {
        return DB::table('coupon')
            ->where('id', $couponId)
            ->where(function ($q) use ($by) {
                $q->whereNull('uses_total')
                    ->orWhereRaw('used_count + ? <= uses_total', [$by]);
            })
            ->whereNull('deleted_at')
            ->increment('used_count', $by);
    }

    public function decrementUsedCount(int $couponId, int $by): void
    {
        DB::table('coupon')
            ->where('id', $couponId)
            ->where('used_count', '>=', $by)
            ->whereNull('deleted_at')
            ->decrement('used_count', $by);
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

    // ===================== CMS (admin) =====================

    /**
     * Danh sách coupon cho CMS. `deleted_at`: 1 = chưa xoá, 0 = chỉ thùng
     * rác, -1 = tất cả (đồng bộ với FormSearch của infuncms).
     */
    public function listForCms(Request $request): LengthAwarePaginator
    {
        $sortable = ['id', 'name', 'code', 'discount', 'date_start', 'date_end', 'sort_order'];
        $sort     = in_array($request->input('sort'), $sortable, true) ? $request->input('sort') : 'id';
        $order    = strtolower((string) $request->input('order', 'desc')) === 'asc' ? 'asc' : 'desc';
        $deleted  = (int) $request->input('deleted_at', -1);
        $keyword  = trim((string) $request->input('keyword', ''));
        $perPage  = max(1, (int) $request->input('per_page', 50));

        $query = $this->resetModel()->newQuery();

        if ($deleted === 0) {
            $query->onlyTrashed();
        } elseif ($deleted === -1) {
            $query->withTrashed();
        }

        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $q->where('name', 'like', '%' . $keyword . '%')
                    ->orWhere('code', 'like', '%' . $keyword . '%');
            });
        }

        if ($request->filled('type')) {
            $query->where('type', (int) $request->input('type'));
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', (int) $request->input('is_active'));
        }

        return $query->orderBy($sort, $order)->orderBy('id', 'desc')->paginate($perPage);
    }

    public function getForCms(int $id): ?Coupon
    {
        return $this->resetModel()
            ->withTrashed()
            ->with([
                'products.description',
                'categories.description',
                'couponHistories' => fn ($q) => $q->with('user')->orderByDesc('id')->limit(200),
            ])
            ->find($id);
    }

    /**
     * `used_count` KHÔNG lấy từ $data — cột denormalize quota do
     * incrementUsedCount()/decrementUsedCount() ghi khi đơn dùng mã. Cho CMS
     * sửa tay là mở đường cho quota lệch với coupon_history.
     *
     * Pivot ghi theo apply_scope: chọn "toàn shop" thì dọn sạch cả 2 bảng
     * pivot, tránh để rác làm scope cũ sống lại nếu sau này đổi lại scope.
     */
    public function saveFromCms(?Coupon $coupon, array $data): Coupon
    {
        return DB::transaction(function () use ($coupon, $data) {
            $productIds  = collect($data['coupon_products'] ?? [])->pluck('id')->map('intval')->unique()->values()->all();
            $categoryIds = collect($data['coupon_categories'] ?? [])->pluck('id')->map('intval')->unique()->values()->all();

            unset($data['coupon_products'], $data['coupon_categories'], $data['used_count']);

            // Cột legacy `total` (đơn tối thiểu kiểu OpenCart) vẫn được
            // resolveCoupon() cũ đọc. Giữ nó bám theo `min_subtotal` để hai
            // đường tính không nói hai chuyện khác nhau — CMS chỉ nhập một ô.
            if (! array_key_exists('total', $data) || $data['total'] === null) {
                $data['total'] = $data['min_subtotal'] ?? 0;
            }

            $coupon ??= new Coupon();
            $coupon->fill($data);
            $coupon->save();

            $scope = (int) ($data['apply_scope'] ?? 0);
            $coupon->products()->sync($scope === 1 ? $productIds : []);
            $coupon->categories()->sync($scope === 2 ? $categoryIds : []);

            $this->flushCache();

            return $coupon->load(['products.description', 'categories.description']);
        });
    }

    public function deleteByIds(array $ids): int
    {
        $affected = $this->resetModel()->whereIn('id', $ids)->delete();
        $this->flushCache();

        return $affected;
    }

    public function restoreByIds(array $ids): int
    {
        $affected = $this->resetModel()->withTrashed()->whereIn('id', $ids)->restore();
        $this->flushCache();

        return $affected;
    }

    public function restoreById(int $id): ?Coupon
    {
        $coupon = $this->resetModel()->withTrashed()->find($id);
        $coupon?->restore();
        $this->flushCache();

        return $coupon?->load(['products.description', 'categories.description']);
    }
}
