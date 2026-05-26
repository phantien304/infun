<?php

namespace App\Http\Controllers\Web;

use App\Data\Output\BlogCategoryDTO;
use App\Data\Output\BlogTagDTO;
use App\Data\Output\ProductDTO;
use App\Data\Output\StoreReviewDTO;
use App\Http\Controllers\Controller;
use App\Repositories\Interfaces\BlogCategoryRepositoryInterface;
use App\Repositories\Interfaces\BlogTagRepositoryInterface;
use App\Repositories\Interfaces\ProductRepositoryInterface;
use App\Repositories\Interfaces\StoreReviewRepositoryInterface;

class StoreReviewController extends Controller
{
    public function __construct(
        protected StoreReviewRepositoryInterface $storeReviewRepo,
        protected BlogCategoryRepositoryInterface $blogCategoryRepo,
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
        $entity = $this->storeReviewRepo->getDetail($id);
        if (empty($entity) || !isset($entity->description)) {
            return $this->toUrl('error.404');
        }
        $entity->increment('viewed');
        $storeReviewDTO = StoreReviewDTO::from($entity)->include('content');
        $this->setBreadcrumb([
            'text' => $storeReviewDTO->title,
            'href' => $storeReviewDTO->url,
            'separator' => false,
        ]);
        $this->processMetaSeo(
            'buildForSeoByData',
            $storeReviewDTO->metaTitle ?: $storeReviewDTO->title,
            $storeReviewDTO->metaDescription
        );

        return $this->render('web.storeReview.index', [
            'entity' => $storeReviewDTO
        ]);
    }

    public function getList()
    {
        $this->processMetaSeo('buildForSeoBySetting', 'seo_title_store_reviews', 'seo_description_store_reviews');

        $this->processRequest();

        $entities = StoreReviewDTO::collect($this->storeReviewRepo->list());

        return $this->render('web.storeReview.list', [
            'entities'    => $entities,
            'sortMenu'    => $this->storeReviewRepo->getSortMenu(),
            'perPageMenu' => $this->storeReviewRepo->getPerPageMenu(),
        ]);
    }

    protected function buildDataCommon()
    {
        $this->setViewData([
            'blogCategories' => BlogCategoryDTO::collect($this->blogCategoryRepo->listAllCached()),
            'blogTags' => BlogTagDTO::collect($this->blogTagRepo->listAllCached()),
            'products' => ProductDTO::collect($this->getProductLatest()),
            'storeReviews' => StoreReviewDTO::collect($this->storeReviewRepo->getStoreReviewsFeatured()),
        ]);
    }

    protected function processRequest()
    {
        request()->merge([
            'per_page' => request()->get('per_page') ?? getModuleConfig('paginate.blog_category'),
        ]);
    }
}
