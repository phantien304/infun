<?php

namespace App\Http\Controllers\Client\InfunStudio;

use App\Repositories\Client\InfunStudio\BannerRepository;
use App\Repositories\Client\InfunStudio\OrderRepository;

class OrderController extends BaseInfunStudioController
{
    public function __construct(
        OrderRepository $orderRepository,
        BannerRepository $bannerRepository
    ) {
        parent::__construct();
        $this->setRepository($orderRepository);
        $this->registerRepository($bannerRepository);
        $this->_breadcrumbs = [
            ['text' => trans('messages.breadcrumbs.home'), 'href' => '/', 'separator' => false],
            ['text' => trans('messages.breadcrumbs.order_search'), 'href' => route('product.getList'), 'separator' => false],
        ];
    }

    public function search()
    {
        $invoiceNo = request()->get('order_code', '');
        $entity = $this->getRepository()->getOrderByInvoiceNo($invoiceNo);

        $this->_processMetaSeo('_buildForSeoBySetting', 'seo_title_cart_search', 'seo_description_cart_search');

        return $this->render('client.infunstudio.order.search', [
            'entity' => $entity,
            'orderCode' => $invoiceNo
        ]);
    }
}
