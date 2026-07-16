<?php

namespace App\Services\Cart;

use App\Data\Output\GiftDTO;
use App\Enums\GiftPickType;
use App\Enums\GiftTriggerType;
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
                } elseif ((int) $gift->pick_type === GiftPickType::Auto->value) {
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

        if ($type === GiftTriggerType::MinSubtotal->value) {
            if ($gift->min_subtotal === null) {
                return null;
            }
            if ($cartSubtotal < (float) $gift->min_subtotal) {
                $missingAmount = (float) $gift->min_subtotal - $cartSubtotal;
                return sprintf(
                    trans('messages.checkout.gift.need_more'),
                    number_format($missingAmount) . (string) getConfigDb('config_currency'),
                );
            }
            return null;
        }

        if ($type === GiftTriggerType::BuySpecificProduct->value) {
            $triggerIds = $gift->triggerProducts->pluck('product_id')->all();
            if (empty(array_intersect($cartProductIds, $triggerIds))) {
                return trans('messages.checkout.gift.not_in_cart');
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
            return trans('messages.checkout.gift.invalid_items');
        }

        $pickType = (int) $gift->pick_type;
        $count = count($itemIds);

        if ($pickType === GiftPickType::Auto->value) {
            if ($count > 0 && $count !== count($allowedIds)) {
                return trans('messages.checkout.gift.auto_take_all');
            }
            return null;
        }

        if ($pickType === GiftPickType::PickOneOfN->value) {
            if ($count !== 1) {
                return trans('messages.checkout.gift.pick_exactly_one');
            }
            return null;
        }

        if ($pickType === GiftPickType::PickUpToN->value) {
            $limit = (int) ($gift->pick_limit ?? 0);
            if ($limit > 0 && $count > $limit) {
                return sprintf(trans('messages.checkout.gift.pick_limit_max'), $limit);
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
                $errors[] = sprintf(trans('messages.checkout.gift.not_found'), $giftId);
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

    public function recordOrderGifts(int $orderId, array $cartItems, int $cartSubtotal): array
    {
        $droppedGifts = [];

        $applied = $this->getAppliedGifts();
        if (empty($applied)) {
            return $droppedGifts;
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
                $droppedGifts[] = (string) $gift->name;
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

        return $droppedGifts;
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
