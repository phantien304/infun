<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\Cart\GiftService;
use App\Services\CartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoint AJAX cho modal Shopee-style "Chọn Quà".
 *
 * Routes:
 *   POST /checkout/gifts/pick    → user submit picks
 *   POST /checkout/gifts/remove  → clear all picks
 *
 * Input pick: `picks[]` array với mỗi entry là JSON string `{gift_id, item_ids[]}`,
 * hoặc field `picks[gift_id_X][]=item_id` dạng nested array.
 */
class CheckoutGiftController extends Controller
{
    public function __construct(
        protected CartService $cart,
        protected GiftService $giftService,
    ) {}

    public function pick(Request $request): JsonResponse
    {
        // Nhận input shape: { picks: { "5": [10, 11], "8": [15] } }
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
            return errValidator($result['errors'][0] ?? 'Không nhận được quà', 200);
        }

        return successData('Success', [
            'errors'         => $result['errors'],
            'applied_count'  => count($picks),
            'reload'         => true,
        ]);
    }

    public function remove(): JsonResponse
    {
        $this->giftService->clearPicks();

        return successData('Success', ['reload' => true]);
    }
}
