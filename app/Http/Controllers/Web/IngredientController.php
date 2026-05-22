<?php

namespace App\Http\Controllers\Client\InfunStudio;

use App\Repositories\Client\InfunStudio\BannerRepository;
use App\Repositories\Client\InfunStudio\BlogCategoryRepository;
use App\Repositories\Client\InfunStudio\BlogTagRepository;
use App\Repositories\Client\InfunStudio\IngredientRepository;
use App\Repositories\Client\InfunStudio\ProductRepository;

class IngredientController extends BaseInfunStudioController
{
    public function __construct(
        IngredientRepository $ingredientRepository,
        BannerRepository $bannerRepository,
        BlogCategoryRepository $blogCategoryRepository,
        BlogTagRepository $blogTagRepository,
        ProductRepository $productRepository
    ) {
        parent::__construct();
        $this->setRepository($ingredientRepository);
        $this->registerRepository($bannerRepository, $blogCategoryRepository, $blogTagRepository, $productRepository);
        $this->_breadcrumbs = [
            ['text' => trans('messages.breadcrumbs.home'), 'href' => '/', 'separator' => false],
            ['text' => trans('messages.breadcrumbs.list_ingredient'), 'href' => route('ingredient.getList'), 'separator' => false],
        ];
    }

    public function getList()
    {
        $this->_processMetaSeo('_buildForSeoBySetting', 'seo_title_ingredient', 'seo_description_ingredient');

        $entities = $this->getRepository()->getListForFrontend($this->getParams());

        return $this->render('client.infunstudio.ingredient.list', [
            'entities' => $entities,
        ]);
    }

    protected function _buildDataCommon()
    {
        $this->setViewData([
            'products' => $this->getProductLatest(),
        ]);
    }
}
