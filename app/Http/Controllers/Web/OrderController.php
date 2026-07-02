<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Repositories\Interfaces\OrderRepositoryInterface;

class OrderController extends Controller
{
    public function __construct(
        protected OrderRepositoryInterface $orderRepo,
    ) {
        $this->breadcrumbs = [
            ['text' => trans('messages.breadcrumbs.home'), 'href' => '/', 'separator' => false],
            ['text' => trans('messages.breadcrumbs.order_search'), 'href' => route('order.search'), 'separator' => false],
        ];
    }

    public function search()
    {
        $orderCode = (string) request()->get('order_code', '');
        $entity = $this->orderRepo->getOrderByInvoiceNo($orderCode);

        $this->processMetaSeo('buildForSeoBySetting', 'seo_title_cart_search', 'seo_description_cart_search');

        return $this->render('web::order.search', [
            'entity'    => $entity,
            'orderCode' => $orderCode,
        ]);
    }
}
