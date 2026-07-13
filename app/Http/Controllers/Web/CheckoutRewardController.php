<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Repositories\Interfaces\UserRewardRepositoryInterface;
use App\Services\Cart\CartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CheckoutRewardController extends Controller
{
    public function __construct(
        protected CartService $cartService,
        protected UserRewardRepositoryInterface $userRewardRepo,
    ) {
    }

    public function apply(Request $request): JsonResponse
    {
        if (getConfigDb('config_reward_point_enabled') == setting('reward_point.disable')) {
            return respondUnprocessable(trans('messages.checkout.reward.disabled'));
        }
        if (! auth()->check()) {
            return respondUnprocessable(trans('messages.checkout.login_required'));
        }
        if (! $this->cartService->hasItems()) {
            return respondUnprocessable(trans('messages.checkout.reward.empty_cart'));
        }

        $points = (int) $request->input('points', 0);
        if ($points <= 0) {
            return respondUnprocessable(trans('messages.checkout.reward.invalid'));
        }

        $balance = $this->userRewardRepo->getTotalPoints((int) getCurrentUserId());
        if ($points > $balance) {
            return respondUnprocessable(
                sprintf(trans('messages.checkout.reward.not_enough'), number_format($balance, 0, '', ','))
            );
        }

        session()->put(getCoreConfig('session.reward'), $points);

        return respondSuccess([
            'points'  => $points,
            'balance' => $balance,
            'reload'  => true,
        ]);
    }

    public function remove(): JsonResponse
    {
        session()->forget(getCoreConfig('session.reward'));

        return respondSuccess(['reload' => true]);
    }

    public function show(): JsonResponse
    {
        return respondSuccess([
            'balance' => auth()->check()
                ? $this->userRewardRepo->getTotalPoints((int) getCurrentUserId())
                : 0,
            'applied' => (int) session()->get(getCoreConfig('session.reward'), 0),
        ]);
    }
}
