<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Repositories\Interfaces\BlogTagRepositoryInterface;
use App\Repositories\Interfaces\ProductRepositoryInterface;
use App\Repositories\Interfaces\StoreReviewRepositoryInterface;

class StoreReviewController extends Controller
{
    public function __construct(
        protected StoreReviewRepositoryInterface $storeReviewRepo,
        protected BlogTagRepositoryInterface $blogTagRepo,
        protected ProductRepositoryInterface $productRepo
    ) {
        $this->breadcrumbs = [
            ['text' => trans('messages.breadcrumbs.home'), 'href' => '/', 'separator' => false],
            ['text' => trans('messages.breadcrumbs.list_store_review'), 'href' => route('storeReview.getList'), 'separator' => false],
        ];
    }

    public function index($id = '')
    {
        $entity = $this->storeReviewRepo->getById($id);
        if (empty($entity) || !isset($entity->description)) {
            return $this->toUrl('error.404');
        }
        $this->updateViewed($entity);

        $this->setBreadcrumb(['text' => $entity->name, 'href' => $entity->getUrlClient(), 'separator' => false]);

        $this->processMetaSeo(
            'buildForSeoByData',
            $entity->description->getMetaTitle(),
            $entity->description->getMetaDescription()
        );

        return $this->render('client.infunstudio.storeReview.index', [
            'entity' => $entity
        ]);
    }

    public function getList()
    {
        $this->processMetaSeo('buildForSeoBySetting', 'seo_title_store_reviews', 'seo_description_store_reviews');

        $this->processRequest();

        $entities = $this->storeReviewRepo->getListForWeb($this->getParams());

        return $this->render('web.storeReview.list', [
            'entities' => $entities
        ]);
    }

    protected function _buildDataCommon()
    {
        $this->setViewData([
            'blogCategories' => $this->getBlogCategories(),
            'blogTags' => $this->getBlogTags(),
            'products' => $this->getProductLatest(),
            'storeReviews' => $this->getStoreReviews(),
        ]);
    }

    protected function processRequest()
    {
        request()->merge([
            'per_page' => request()->get('per_page') ?? getModuleConfig('paginate.blog_category'),
        ]);
    }
}
