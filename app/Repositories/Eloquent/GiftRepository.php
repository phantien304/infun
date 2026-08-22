<?php

namespace App\Repositories\Eloquent;

use App\Enums\GiftTriggerType;
use App\Models\Entities\Gift;
use App\Models\Entities\GiftItem;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Concerns\CacheableRepository;
use App\Repositories\Interfaces\GiftRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class GiftRepository extends QueryableRepository implements GiftRepositoryInterface
{
    use CacheableRepository;

    public function model(): string
    {
        return Gift::class;
    }

    public function listActive(): Collection
    {
        return $this->rememberCache(
            getCoreConfig('gift.cache.key_active'),
            fn () => $this->resetModel()
                ->newQuery()
                ->active()
                ->with([
                    'items.product.description',
                    'items.variant.description',
                    'triggerProducts',
                ])
                ->orderByDesc('sort_order')
                ->orderBy('id')
                ->get(),
            getCoreConfig('time.cache'),
            tags: [getCoreConfig('gift.cache.tag_root')],
        );
    }

    public function findActiveById(int $giftId): ?Gift
    {
        if ($giftId <= 0) {
            return null;
        }

        return $this->resetModel()
            ->newQuery()
            ->active()
            ->where('id', $giftId)
            ->with(['items.product.description', 'items.variant.description', 'triggerProducts'])
            ->first();
    }

    public function incrementUsedCount(int $giftId, int $by = 1): int
    {
        return DB::table('gift')
            ->where('id', $giftId)
            ->where(function ($q) use ($by) {
                $q->whereNull('uses_total')
                    ->orWhereRaw('used_count + ? <= uses_total', [$by]);
            })
            ->whereNull('deleted_at')
            ->increment('used_count', $by);
    }

    public function decrementUsedCount(int $giftId): void
    {
        DB::table('gift')
            ->where('id', $giftId)
            ->where('used_count', '>=', 1)
            ->whereNull('deleted_at')
            ->decrement('used_count');
    }

    public function flushCache(): void
    {
        $this->forgetCacheTagged([getCoreConfig('gift.cache.tag_root')]);
    }

    // ===================== CMS (admin) =====================

    public function listForCms(Request $request): LengthAwarePaginator
    {
        $sortable = ['id', 'name', 'trigger_type', 'date_start', 'date_end', 'sort_order'];
        $sort     = in_array($request->input('sort'), $sortable, true) ? $request->input('sort') : 'id';
        $order    = strtolower((string) $request->input('order', 'desc')) === 'asc' ? 'asc' : 'desc';
        $deleted  = (int) $request->input('deleted_at', -1);
        $keyword  = trim((string) $request->input('keyword', ''));
        $perPage  = max(1, (int) $request->input('per_page', 50));

        $query = $this->resetModel()->newQuery()->withCount('items');

        if ($deleted === 0) {
            $query->onlyTrashed();
        } elseif ($deleted === -1) {
            $query->withTrashed();
        }

        if ($keyword !== '') {
            $query->where('name', 'like', '%' . $keyword . '%');
        }

        if ($request->filled('trigger_type')) {
            $query->where('trigger_type', (int) $request->input('trigger_type'));
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', (int) $request->input('is_active'));
        }

        return $query->orderBy($sort, $order)->orderBy('id', 'desc')->paginate($perPage);
    }

    public function getForCms(int $id): ?Gift
    {
        return $this->resetModel()
            ->withTrashed()
            ->with([
                'items.product.description',
                'items.variant.description',
                'triggerProducts.product.description',
            ])
            ->find($id);
    }

    /**
     * Ghi gift + gift_item + gift_trigger_product trong 1 transaction.
     *
     * gift_item KHÔNG xoá-rồi-chèn-lại: `order_gift.gift_item_id` là FK
     * RESTRICT (audit trail đơn đã nhận quà). Nên chỉ xoá đúng dòng admin bỏ
     * đi, và nếu dòng đó đã có đơn tham chiếu thì dừng với thông báo rõ —
     * thay vì để FK ném lỗi SQL khó hiểu ở tầng trên.
     *
     * gift_trigger_product không có FK ngược nào ⇒ sync thẳng.
     */
    public function saveFromCms(?Gift $gift, array $data): Gift
    {
        return DB::transaction(function () use ($gift, $data) {
            $items       = (array) ($data['gift_items'] ?? []);
            $triggerIds  = collect($data['gift_trigger_products'] ?? [])
                ->pluck('id')->map('intval')->unique()->values()->all();

            unset($data['gift_items'], $data['gift_trigger_products'], $data['used_count']);

            $gift ??= new Gift();
            $gift->fill($data);
            $gift->save();

            $this->syncItems($gift, $items);
            $this->syncTriggerProducts($gift, (int) $gift->trigger_type, $triggerIds);

            $this->flushCache();

            return $gift->load([
                'items.product.description',
                'items.variant.description',
                'triggerProducts.product.description',
            ]);
        });
    }

    protected function syncItems(Gift $gift, array $items): void
    {
        $keptIds = [];

        foreach ($items as $index => $item) {
            $row = GiftItem::firstOrNew([
                'gift_id'            => $gift->id,
                'product_id'         => (int) $item['product_id'],
                'product_variant_id' => $item['product_variant_id'] ?? null,
            ]);
            $row->quantity   = (int) ($item['quantity'] ?? 1);
            $row->sort_order = (int) ($item['sort_order'] ?? $index);
            $row->save();

            $keptIds[] = (int) $row->id;
        }

        $removedIds = GiftItem::where('gift_id', $gift->id)
            ->whereNotIn('id', $keptIds ?: [0])
            ->pluck('id')
            ->map('intval')
            ->all();

        if (empty($removedIds)) {
            return;
        }

        $referenced = DB::table('order_gift')->whereIn('gift_item_id', $removedIds)->exists();
        abort_if($referenced, 422, trans('messages.cms.gift.item_in_use'));

        GiftItem::whereIn('id', $removedIds)->delete();
    }

    protected function syncTriggerProducts(Gift $gift, int $triggerType, array $productIds): void
    {
        // trigger_type != buy_specific_product ⇒ danh sách SP kích hoạt là
        // rác: dọn sạch để không "sống lại" nếu sau này admin đổi lại type.
        if ($triggerType !== GiftTriggerType::BuySpecificProduct->value) {
            $productIds = [];
        }

        DB::table('gift_trigger_product')->where('gift_id', $gift->id)->delete();

        if (empty($productIds)) {
            return;
        }

        DB::table('gift_trigger_product')->insert(
            array_map(fn (int $pid) => ['gift_id' => $gift->id, 'product_id' => $pid], $productIds),
        );
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

    public function restoreById(int $id): ?Gift
    {
        $gift = $this->resetModel()->withTrashed()->find($id);
        $gift?->restore();
        $this->flushCache();

        return $gift?->load(['items.product.description', 'triggerProducts.product.description']);
    }
}
