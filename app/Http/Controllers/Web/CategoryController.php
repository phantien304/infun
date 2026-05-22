<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;

class CategoryController extends Controller
{
    public function __construct(
        CategoryRepository $categoryRepository,
        ProductRepository $productRepository
    ) {
        parent::__construct();
        $this->setRepository($categoryRepository);
        $this->registerRepository($productRepository);
        $this->_breadcrumbs = [
            ['text' => trans('messages.breadcrumbs.home'), 'href' => '/', 'separator' => false],
            ['text' => trans('messages.breadcrumbs.list_product'), 'href' => route('product.getList'), 'separator' => false],
        ];
    }

    public function index($id)
    {
        $entity = $this->getRepository()->getDetail($id);
        if (empty($entity) || !isset($entity->categoryDescription)) {
            return $this->_to('error.404');
        }

        $this->setBreadcrumb(['text' => $entity->categoryDescription->title, 'href' => $entity->categoryDescription->getUrlClient(), 'separator' => false]);

        $this->_processMetaSeo(
            '_buildForSeoByData',
            $entity->categoryDescription->getMetaTitle(),
            $entity->categoryDescription->getMetaDescription()
        );

        $this->_processRequest($id);

        $entities = $this->fetchRepository(ProductRepository::class)->getListForFrontend($this->getParams());

        return $this->render('client.infunstudio.category.list', [
            'entity' => $entity,
            'entities' => $entities,
        ]);
    }

    protected function _buildDataCommon()
    {
        $this->setViewData([
            'products' => $this->getProductLatest(),
        ]);
    }

    protected function _processRequest($id)
    {
        if (!request()->has('product_category')) {
            request()->merge([
                'product_category' => ['category_id_eq' => $id]
            ]);
        }
    }
}
