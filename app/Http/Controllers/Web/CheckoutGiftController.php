<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\Cart\GiftService;
use App\Services\CartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CheckoutGiftController extends Controller
{
    public function __construct(
        protected CartService $cart,
        protected GiftService $giftService,
    ) {}

    public function pick(Request $request): JsonResponse
    {
        $rawPicks = (array) $request->input('picks', []);
        $picks = [];
        foreach ($rawPicks as $giftId => $itemIds) {
            $picks[] = [
                'gift_id'  => (int) $giftId,
                'item_ids' => array_values(array_filter(array_map('intval', (array) $itemIds), fn ($i) => $i > 0)),
            ];
        }

        $items    = $this->cart->getItems();
        $subtotal = (int) $this->cart->getSubtotal();

        $result = $this->giftService->applyPicks($picks, $items, $subtotal);

        if (! $result['ok']) {
            return respondUnprocessable($result['errors'][0] ?? trans('messages.checkout.gift_not_received'));
        }

        return respondSuccess([
            'errors'         => $result['errors'],
            'applied_count'  => count($picks),
            'reload'         => true,
        ]);
    }

    public function remove(): JsonResponse
    {
        $this->giftService->clearPicks();

        return respondSuccess(['reload' => true]);
    }
}
