<?php

namespace App\Http\Controllers\Web;

use App\Data\Output\ManufacturerDTO;
use App\Data\Output\ProductDTO;
use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;

class ManufacturerController extends Controller
{
    public function __construct()
    {
        $this->breadcrumbs = [
            ['text' => trans('messages.breadcrumbs.home'), 'href' => '/', 'separator' => false],
            ['text' => trans('messages.breadcrumbs.list_product'), 'href' => route('product.getList'), 'separator' => false],
        ];
    }

    public function index($id = '')
    {
        $id = (int) $id;
        $entity = $this->manufacturerRepo->getManufacturerDetail($id);
        if (! $entity) {
            return $this->toUrl('error.404');
        }

        $manufacturer = ManufacturerDTO::from($entity);
        $this->setBreadcrumb(['text' => $manufacturer->name, 'href' => $manufacturer->url, 'separator' => false]);
        $this->processMetaSeo(
            'buildForSeoByData',
            (string) ($manufacturer->metaTitle ?: $manufacturer->name),
            (string) $manufacturer->metaDescription,
        );

        $productList = $this->productRepo->list(
            null,
            null,
            fn (Builder $q) => $q->where('product.manufacturer_id', $id),
        );
        $productList->setCollection(
            $productList->getCollection()->map(fn ($model) => ProductDTO::from($model))
        );

        return $this->render('web::manufacturer.list', [
            'entity'         => $manufacturer,
            'entities'       => $productList,
            'latestProducts' => ProductDTO::collect($this->getProductLatest()),
            'sortMenu'       => $this->productRepo->getSortMenu(),
            'perPageMenu'    => $this->productRepo->getPerPageMenu(),
        ]);
    }
}
