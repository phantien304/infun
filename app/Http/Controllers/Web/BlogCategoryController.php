<?php

namespace App\Http\Controllers\Web;

use App\Data\Output\BlogCategoryDTO;
use App\Data\Output\BlogDTO;
use App\Data\Output\BlogTagDTO;
use App\Data\Output\ProductDTO;
use App\Data\Output\StoreReviewDTO;
use App\Http\Controllers\Controller;
use App\Models\Entities\BlogCategory;
use App\Repositories\Interfaces\BlogCategoryRepositoryInterface;
use App\Repositories\Interfaces\BlogRepositoryInterface;
use App\Repositories\Interfaces\BlogTagRepositoryInterface;
use App\Repositories\Interfaces\StoreReviewRepositoryInterface;

class BlogCategoryController extends Controller
{
    public function __construct(
        protected BlogRepositoryInterface $blogRepo,
        protected BlogCategoryRepositoryInterface $blogCategoryRepo,
        protected BlogTagRepositoryInterface $blogTagRepo,
        protected StoreReviewRepositoryInterface $storeReviewRepo,
    ) {
        $this->breadcrumbs = [
            ['text' => trans('messages.breadcrumbs.home'), 'href' => '/', 'separator' => false],
            ['text' => trans('messages.breadcrumbs.list_blog'), 'href' => route('blog.getList'), 'separator' => false],
        ];
    }

    public function index($id = '')
    {
        $category = BlogCategory::with([
            'description' => fn ($q) => $q->where('language_code', app()->getLocale()),
        ])->find($id);

        if (! $category || ! $category->description) {
            return redirect()->route('error.404');
        }

        $categoryDTO = BlogCategoryDTO::from($category);
        $desc = $category->description;

        $this->setBreadcrumb([
            'text' => $categoryDTO->title,
            'href' => $categoryDTO->url,
            'separator' => false,
        ]);
        $this->processMetaSeo(
            'buildForSeoByData',
            (string) ($desc->meta_title ?: $categoryDTO->title),
            (string) ($desc->meta_description ?: '')
        );

        request()->merge([
            'filter'   => array_merge((array) request('filter', []), ['category_id' => (int) $id]),
            'per_page' => request('per_page') ?? getModuleConfig('paginate.blog_category'),
        ]);

        $entities = $this->blogRepo->list();

        return $this->render('web::blog.list', [
            'title'       => $categoryDTO->title,
            'entities'    => BlogDTO::collect($entities),
            'sortMenu'    => $this->blogRepo->getSortMenu(),
            'perPageMenu' => $this->blogRepo->getPerPageMenu(),
        ]);
    }

    protected function buildDataCommon()
    {
        $this->setViewData([
            'blogCategories' => BlogCategoryDTO::collect($this->blogCategoryRepo->listAllCached()),
            'blogTags'       => BlogTagDTO::collect($this->blogTagRepo->listAllCached()),
            'products'       => ProductDTO::collect($this->getProductLatest()),
            'storeReviews'   => StoreReviewDTO::collect($this->storeReviewRepo->getStoreReviewsFeatured()),
        ]);
    }
}
