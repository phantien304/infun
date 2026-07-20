<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class CartBadgeController extends Controller
{
    public function index(): JsonResponse
    {
        $cart = (int) session()->get(getCoreConfig('session.cart_header'), 0);

        $wishlist = auth()->check()
            ? (int) session()->get(getCoreConfig('session.total_wishlist'), 0)
            : 0;

        return respondSuccess(['cart' => $cart, 'wishlist' => $wishlist])
            ->header('Cache-Control', 'no-store, private');
    }
}
