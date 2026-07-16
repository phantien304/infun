<?php

namespace App\Services\Cart;

use App\Data\Output\GiftDTO;
use App\Models\Entities\Gift;
use App\Repositories\Interfaces\GiftRepositoryInterface;
use App\Repositories\Interfaces\OrderGiftRepositoryInterface;
use Illuminate\Support\Collection;

class GiftService
{
    public function __construct(
        protected GiftRepositoryInterface $giftRepo,
        protected OrderGiftRepositoryInterface $orderGiftRepo,
    ) {
    }

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

        session()->put(getCoreConfig('session.applied_gifts'), $accepted);

        return ['ok' => empty($errors), 'errors' => $errors];
    }

    public function clearPicks(): void
    {
        session()->forget(getCoreConfig('session.applied_gifts'));
    }

    public function pruneInvalid(array $cartItems, int $cartSubtotal): void
    {
        $applied = $this->getAppliedGifts();
        if (empty($applied)) {
            return;
        }

        $cartProductIds = array_unique(array_map(fn ($i) => (int) ($i['id'] ?? 0), $cartItems));
        $kept = [];

        foreach ($applied as $entry) {
            $giftId = (int) ($entry['gift_id'] ?? 0);
            $itemIds = (array) ($entry['item_ids'] ?? []);
            if ($giftId <= 0 || empty($itemIds)) {
                continue;
            }

            $gift = $this->giftRepo->findActiveById($giftId);
            if (! $gift
                || $this->validateTrigger($gift, $cartSubtotal, $cartProductIds) !== null
                || $this->validatePicks($gift, $itemIds) !== null) {
                continue;
            }

            $kept[] = $entry;
        }

        if (count($kept) !== count($applied)) {
            session()->put(getCoreConfig('session.applied_gifts'), array_values($kept));
        }
    }

    public function getAppliedGifts(): array
    {
        $raw = (array) session()->get(getCoreConfig('session.applied_gifts'), []);
        return array_values(array_filter($raw, 'is_array'));
    }

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

    public function recordOrderGifts(int $orderId, array $cartItems, int $cartSubtotal): void
    {
        $applied = $this->getAppliedGifts();
        if (empty($applied)) {
            return;
        }

        $cartProductIds = array_unique(array_map(fn ($i) => (int) ($i['id'] ?? 0), $cartItems));

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

            if ($this->validateTrigger($gift, $cartSubtotal, $cartProductIds) !== null
                || $this->validatePicks($gift, $itemIds) !== null) {
                continue;
            }

            if ($this->giftRepo->incrementUsedCount($giftId) === 0) {
                logError(sprintf(
                    'recordOrderGifts: gift %d exhausted at commit — order %d proceeds without gift',
                    $giftId,
                    $orderId,
                ));

                continue;
            }

            $itemQtys = $gift->items->whereIn('id', $itemIds)->pluck('quantity', 'id')->all();

            foreach ($itemIds as $itemId) {
                $itemId = (int) $itemId;
                if (! isset($itemQtys[$itemId])) {
                    continue;
                }
                $this->orderGiftRepo->insertItem(
                    $orderId,
                    $giftId,
                    $itemId,
                    (int) $itemQtys[$itemId],
                );
            }
        }
    }

    public function revertOrderGifts(int $orderId): void
    {
        $gifts = $this->orderGiftRepo->giftIdsForOrder($orderId);
        if ($gifts->isEmpty()) {
            return;
        }

        foreach ($gifts->groupBy('gift_id') as $giftId => $rows) {
            $this->giftRepo->decrementUsedCount((int) $giftId);
        }

        $this->orderGiftRepo->deleteForOrder($orderId);
    }
}
