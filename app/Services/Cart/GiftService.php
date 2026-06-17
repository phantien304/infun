<?php

namespace App\Services\Cart;

use App\Data\Output\GiftDTO;
use App\Models\Entities\Gift;
use App\Models\Entities\OrderGift;
use App\Repositories\Interfaces\GiftRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Gift orchestration cho cart Shopee-style.
 *
 * Trách nhiệm:
 *  - `listForCart`     : list gift + cờ availableToCart + reason + picked items
 *  - `validatePicks`   : check pick_type rule (1_of_n, up_to_n, auto)
 *  - `applyPicks`      : set session('checkout.applied_gifts')
 *  - `clearPicks`      : reset session
 *  - `recordOrderGifts`: persist order_gift rows + increment used_count
 *  - `revertOrderGifts`: rollback khi order cancel
 *
 * Session shape: `checkout.applied_gifts` = [
 *   ['gift_id' => 5, 'item_ids' => [10, 11]],
 *   ['gift_id' => 8, 'item_ids' => [15]],
 * ]
 *
 * Auto-pick gift (`pick_type=0`): không cần user thao tác — server tự pick
 * mọi gift_item vào session khi list, nhưng chỉ persist vào order khi user
 * submit checkout.
 */
class GiftService
{
    public function __construct(
        protected GiftRepositoryInterface $giftRepo,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $cartItems  từ CartService::getItems
     * @return Collection<int, GiftDTO>
     */
    public function listForCart(array $cartItems, int $cartSubtotal): Collection
    {
        $gifts = $this->giftRepo->listActive();
        $applied = $this->getAppliedGifts();
        $appliedByGift = collect($applied)->keyBy('gift_id');

        $cartProductIds = array_unique(array_map(
            fn ($item) => (int) ($item['id'] ?? 0),
            $cartItems,
        ));

        return $gifts->map(function (Gift $gift) use ($cartSubtotal, $cartProductIds, $appliedByGift) {
            $reason = $this->validateTrigger($gift, $cartSubtotal, $cartProductIds);
            $available = $reason === null;

            // Pre-fill picked items từ session, nếu auto-pick thì pre-select tất cả items.
            $pickedItemIds = [];
            if ($available) {
                $sessionEntry = $appliedByGift->get((int) $gift->id);
                if ($sessionEntry) {
                    $pickedItemIds = (array) ($sessionEntry['item_ids'] ?? []);
                } elseif ((int) $gift->pick_type === (int) getCoreConfig('gift.pick_type.auto')) {
                    $pickedItemIds = $gift->items->pluck('id')->all();
                }
            }

            return GiftDTO::fromModel($gift, $available, $reason, $pickedItemIds);
        })->sortBy([
            ['availableToCart', 'desc'],
        ])->values();
    }

    /**
     * Trigger check (KHÔNG check pick — đó là service validatePicks).
     */
    public function validateTrigger(Gift $gift, int $cartSubtotal, array $cartProductIds): ?string
    {
        $type = (int) $gift->trigger_type;

        if ($type === (int) getCoreConfig('gift.trigger_type.min_subtotal')) {
            if ($gift->min_subtotal === null) {
                return null;
            }
            if ($cartSubtotal < (float) $gift->min_subtotal) {
                $missingAmount = (float) $gift->min_subtotal - $cartSubtotal;
                return 'Cần mua thêm ' . number_format($missingAmount) . (string) getConfigDb('config_currency');
            }
            return null;
        }

        if ($type === (int) getCoreConfig('gift.trigger_type.buy_specific_product')) {
            $triggerIds = $gift->triggerProducts->pluck('product_id')->all();
            if (empty(array_intersect($cartProductIds, $triggerIds))) {
                return 'Chưa có SP áp dụng trong giỏ';
            }
            return null;
        }

        return null;
    }

    /**
     * Validate pick rule. Trả NULL nếu OK hoặc reason string.
     *
     * @param  array<int, int>  $itemIds
     */
    public function validatePicks(Gift $gift, array $itemIds): ?string
    {
        $itemIds = array_values(array_unique(array_map('intval', $itemIds)));
        $allowedIds = $gift->items->pluck('id')->map('intval')->all();
        $invalid = array_diff($itemIds, $allowedIds);
        if (! empty($invalid)) {
            return 'Quà không hợp lệ';
        }

        $pickType = (int) $gift->pick_type;
        $count = count($itemIds);

        if ($pickType === (int) getCoreConfig('gift.pick_type.auto')) {
            // auto: cho phép pick TẤT CẢ items, KHÔNG pick từng phần.
            if ($count > 0 && $count !== count($allowedIds)) {
                return 'Quà tự động — phải nhận tất cả';
            }
            return null;
        }

        if ($pickType === (int) getCoreConfig('gift.pick_type.pick_1_of_n')) {
            if ($count !== 1) {
                return 'Phải chọn đúng 1 quà';
            }
            return null;
        }

        if ($pickType === (int) getCoreConfig('gift.pick_type.pick_up_to_n')) {
            $limit = (int) ($gift->pick_limit ?? 0);
            if ($limit > 0 && $count > $limit) {
                return "Chỉ được chọn tối đa {$limit} quà";
            }
            return null;
        }

        return null;
    }

    /**
     * Apply user picks vào session sau khi validate.
     *
     * @param  array<int, array{gift_id:int, item_ids:array<int,int>}>  $picks
     * @return array{ok: bool, errors: array<int, string>}
     */
    public function applyPicks(array $picks, array $cartItems, int $cartSubtotal): array
    {
        $errors = [];
        $accepted = [];
        $cartProductIds = array_unique(array_map(fn ($i) => (int) ($i['id'] ?? 0), $cartItems));

        foreach ($picks as $pick) {
            $giftId = (int) ($pick['gift_id'] ?? 0);
            $itemIds = (array) ($pick['item_ids'] ?? []);
            if ($giftId <= 0) {
                continue;
            }

            $gift = $this->giftRepo->findActiveById($giftId);
            if (! $gift) {
                $errors[] = "Quà #{$giftId} không tồn tại hoặc không còn hiệu lực";
                continue;
            }

            $triggerReason = $this->validateTrigger($gift, $cartSubtotal, $cartProductIds);
            if ($triggerReason !== null) {
                $errors[] = "{$gift->name}: {$triggerReason}";
                continue;
            }

            $pickReason = $this->validatePicks($gift, $itemIds);
            if ($pickReason !== null) {
                $errors[] = "{$gift->name}: {$pickReason}";
                continue;
            }

            $accepted[] = [
                'gift_id'  => $giftId,
                'item_ids' => array_values(array_map('intval', $itemIds)),
            ];
        }

        session()->put('checkout.applied_gifts', $accepted);

        return ['ok' => empty($errors), 'errors' => $errors];
    }

    public function clearPicks(): void
    {
        session()->forget('checkout.applied_gifts');
    }

    public function getAppliedGifts(): array
    {
        $raw = (array) session()->get('checkout.applied_gifts', []);
        return array_values(array_filter($raw, 'is_array'));
    }

    /**
     * Resolve gift picks ở session thành flat list để render trong danh sách
     * SP của cart (Shopee-style "Quà tặng kèm" section). Mỗi entry chứa thông
     * tin SP/variant + label gift gốc.
     *
     * Trả mảng:
     *   [
     *     {gift_id, gift_name, item_id, product_id, variant_id, name, image, quantity, variant_label},
     *     ...
     *   ]
     *
     * Không validate trigger ở đây — giả định caller đã pass cart context để
     * pick → validate xong → session chỉ chứa pick hợp lệ. Khi cart thay đổi
     * làm trigger fail, listForCart sẽ re-validate và filter ở render time.
     *
     * @return array<int, array<string, mixed>>
     */
    public function resolveGiftDisplayItems(): array
    {
        $applied = $this->getAppliedGifts();
        if (empty($applied)) {
            return [];
        }

        $items = [];
        foreach ($applied as $entry) {
            $giftId = (int) ($entry['gift_id'] ?? 0);
            $itemIds = (array) ($entry['item_ids'] ?? []);
            if ($giftId <= 0 || empty($itemIds)) {
                continue;
            }

            $gift = $this->giftRepo->findActiveById($giftId);
            if (! $gift) {
                continue;
            }

            foreach ($gift->items->whereIn('id', array_map('intval', $itemIds)) as $item) {
                $product = $item->product;
                $variant = $item->variant;
                if (! $product) {
                    continue;
                }
                $items[] = [
                    'gift_id'       => (int) $gift->id,
                    'gift_name'     => (string) $gift->name,
                    'item_id'       => (int) $item->id,
                    'product_id'    => (int) $item->product_id,
                    'variant_id'    => $item->product_variant_id !== null ? (int) $item->product_variant_id : null,
                    'name'          => (string) ($product->description?->name ?? ''),
                    'image'         => $product->image,
                    'quantity'      => (int) $item->quantity,
                    'variant_label' => $variant?->description?->name,
                ];
            }
        }
        return $items;
    }

    /**
     * Persist order_gift rows + increment gift.used_count khi order tạo.
     * Gọi từ CreateOrderService trong cùng transaction.
     */
    public function recordOrderGifts(int $orderId): void
    {
        $applied = $this->getAppliedGifts();
        if (empty($applied)) {
            return;
        }

        foreach ($applied as $entry) {
            $giftId = (int) ($entry['gift_id'] ?? 0);
            $itemIds = (array) ($entry['item_ids'] ?? []);
            if ($giftId <= 0 || empty($itemIds)) {
                continue;
            }

            $gift = $this->giftRepo->findActiveById($giftId);
            if (! $gift) {
                continue;
            }

            $itemQtys = $gift->items->whereIn('id', $itemIds)->pluck('quantity', 'id')->all();

            foreach ($itemIds as $itemId) {
                $itemId = (int) $itemId;
                if (! isset($itemQtys[$itemId])) {
                    continue;
                }
                OrderGift::query()->insertOrIgnore([
                    'order_id'     => $orderId,
                    'gift_id'      => $giftId,
                    'gift_item_id' => $itemId,
                    'quantity'     => (int) $itemQtys[$itemId],
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ]);
            }

            DB::table('gift')->where('id', $giftId)->increment('used_count');
        }
    }

    /**
     * Revert quota khi order huỷ. Gọi từ AccountService::cancelOrder.
     * order_gift CASCADE delete khi orders xoá thật, nhưng ở luồng cancel
     * (UPDATE status) row vẫn còn → đếm rồi decrement used_count manual.
     */
    public function revertOrderGifts(int $orderId): void
    {
        $gifts = OrderGift::query()
            ->forOrder($orderId)
            ->get(['gift_id']);
        if ($gifts->isEmpty()) {
            return;
        }

        foreach ($gifts->groupBy('gift_id') as $giftId => $rows) {
            DB::table('gift')
                ->where('id', (int) $giftId)
                ->where('used_count', '>=', 1)
                ->decrement('used_count');
        }

        // Xoá order_gift rows để không double-revert nếu cancel idempotent.
        OrderGift::query()->forOrder($orderId)->delete();
    }
}
