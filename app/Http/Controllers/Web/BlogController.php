<?php

namespace App\Http\Controllers\Web;

use App\Data\Output\BlogCategoryDTO;
use App\Data\Output\BlogDTO;
use App\Data\Output\BlogTagDTO;
use App\Data\Output\ProductDTO;
use App\Http\Controllers\Controller;
use App\Repositories\Interfaces\BlogCategoryRepositoryInterface;
use App\Repositories\Interfaces\BlogRepositoryInterface;
use App\Repositories\Interfaces\BlogTagRepositoryInterface;
use App\Repositories\Interfaces\ProductRepositoryInterface;
use App\Repositories\Interfaces\StoreReviewRepositoryInterface;

class BlogController extends Controller
{
    public function __construct(
        protected BlogRepositoryInterface $blogRepo,
        protected BlogCategoryRepositoryInterface $blogCategoryRepo,
        protected BlogTagRepositoryInterface $blogTagRepo,
        protected StoreReviewRepositoryInterface $storeReviewRepo,
        protected ProductRepositoryInterface $productRepository
    ) {
        $this->breadcrumbs = [
            ['text' => trans('messages.breadcrumbs.home'), 'href' => '/', 'separator' => false],
            ['text' => trans('messages.breadcrumbs.list_blog'), 'href' => route('blog.getList'), 'separator' => false],
        ];
    }
    public function index($id = '')
    {
        $entity = $this->blogRepo->getDetail($id);
        if (empty($entity) || !isset($entity->blogDescription)) {
            return $this->toUrl('error.404');
        }
        $this->updateViewed($entity);

        $this->setBreadcrumb(['text' => $entity->blogDescription->title, 'href' => $entity->blogDescription->getUrlClient(), 'separator' => false]);

        $this->processMetaSeo('buildForSeoByData', $entity->blogDescription->getMetaTitle(), $entity->blogDescription->getMetaDescription());

        return $this->render('web.blog.index', [
            'entity' => $entity
        ]);
    }
    public function getList()
    {
        $this->processMetaSeo('buildForSeoBySetting', 'seo_title_blogs', 'seo_description_blogs');

        $this->processRequest();

        $entities = $this->blogRepo->list();

        return $this->render('web.blog.list', [
            'entities' => BlogDTO::collect($entities)
        ]);
    }
    protected function buildDataCommon()
    {
        $this->setViewData([
            'blogCategories' => BlogCategoryDTO::collect($this->blogCategoryRepo->listAllCached()),
            'blogTags' => BlogTagDTO::collect($this->blogTagRepo->listAllCached()),
            'products' => ProductDTO::collect($this->getProductLatest()),
            'storeReviews' => $this->storeReviewRepo->getStoreReviewsFeatured(),
        ]);
    }
    protected function processRequest()
    {
        request()->merge([
            'per_page' => request()->get('per_page') ?? getModuleConfig('paginate.blog_category'),
        ]);
    }
}
