<?php

namespace App\Http\Controllers\Web;

use App\Data\Output\BlogCategoryDTO;
use App\Data\Output\BlogDTO;
use App\Data\Output\BlogTagDTO;
use App\Data\Output\ProductDTO;
use App\Data\Output\StoreReviewDTO;
use App\Http\Controllers\Controller;
use App\Repositories\Interfaces\BlogCategoryRepositoryInterface;
use App\Repositories\Interfaces\BlogRepositoryInterface;
use App\Repositories\Interfaces\BlogTagRepositoryInterface;
use App\Repositories\Interfaces\ProductRepositoryInterface;
use App\Repositories\Interfaces\StoreReviewRepositoryInterface;

class BlogController extends Controller
{
    public function __construct(
        protected StoreReviewRepositoryInterface $storeReviewRepo,
        protected BlogCategoryRepositoryInterface $blogCategoryRepo,
        protected BlogRepositoryInterface $blogRepo,
        protected BlogTagRepositoryInterface $blogTagRepo,
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
        if (empty($entity) || empty($entity->description)) {
            return $this->toUrl('error.404');
        }
        $entity->increment('viewed');
        $blogDTO = BlogDTO::from($entity)->include('content', 'tag');
        $this->setBreadcrumb([
            'text' => $blogDTO->title,
            'href' => $blogDTO->url,
            'separator' => false,
        ]);
        $this->processMetaSeo(
            'buildForSeoByData',
            $blogDTO->metaTitle ?: $blogDTO->title,
            $blogDTO->metaDescription ?: $blogDTO->description
        );

        return $this->render('web::blog.index', [
            'entity' => $blogDTO,
        ]);
    }
    public function getList()
    {
        $this->processMetaSeo('buildForSeoBySetting', 'seo_title_blogs', 'seo_description_blogs');

        $this->processRequest();

        $entities = $this->blogRepo->list();
        return $this->render('web::blog.list', [
            'entities' => BlogDTO::collect($entities),
            'sortMenu'    => $this->blogRepo->getSortMenu(),
            'perPageMenu' => $this->blogRepo->getPerPageMenu(),
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
