<?php

namespace App\Http\Controllers\Web;

use App\Data\Output\CategoryDTO;
use App\Data\Output\ProductDTO;
use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;

class CategoryController extends Controller
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
        $entity = $this->categoryRepo->getCategoryDetail($id);
        if (! $entity || ! $entity->description) {
            return $this->toUrl('error.404');
        }

        $category = CategoryDTO::from($entity);
        $this->setBreadcrumb(['text' => $category->title, 'href' => $category->url, 'separator' => false]);
        $this->processMetaSeo('buildForSeoByData', (string) $category->metaTitle(), (string) $category->metaDescription());

        $productList = $this->productRepo->list(
            null,
            null,
            fn (Builder $q) => $q->whereHas(
                'productCategories',
                fn ($qq) => $qq->where('category_id', $id)
            ),
        );
        $productList->setCollection(
            $productList->getCollection()->map(fn ($m) => ProductDTO::from($m))
        );

        return $this->render('web::category.list', [
            'entity'         => $category,
            'entities'       => $productList,
            'latestProducts' => ProductDTO::collect($this->getProductLatest()),
            'sortMenu'       => $this->productRepo->getSortMenu(),
            'perPageMenu'    => $this->productRepo->getPerPageMenu(),
        ]);
    }
}
