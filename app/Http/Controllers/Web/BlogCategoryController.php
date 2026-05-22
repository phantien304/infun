<?php

namespace App\Http\Controllers\Web;


use App\Http\Controllers\Controller;
use App\Model\Entities\BlogCategory;
use App\Repositories\Client\InfunStudio\BlogCategoryRepository;
use App\Repositories\Client\InfunStudio\BlogRepository;
use App\Repositories\Client\InfunStudio\BlogTagRepository;
use App\Repositories\Client\InfunStudio\ProductRepository;

class BlogCategoryController extends Controller
{
    public function __construct(
        BlogCategoryRepository $blogCategoryRepository,
        BlogTagRepository $blogTagRepository,
        BlogRepository $blogRepository,
        ProductRepository $productRepository
    ) {
        parent::__construct();
        $this->setRepository($blogCategoryRepository);
        $this->registerRepository($blogTagRepository, $productRepository, $blogRepository);
        $this->_breadcrumbs = [
            ['text' => trans('messages.breadcrumbs.home'), 'href' => '/', 'separator' => false],
            ['text' => trans('messages.breadcrumbs.list_blog'), 'href' => route('blog.getList'), 'separator' => false],
        ];
    }

    public function index($id = '')
    {
        $entity = BlogCategory::where('id', $id)
            ->with([
                'blogCategoryDescription' => function ($q) {
                    $q->where('language_code', app()->getLocale());
                }
            ])->first();
        if (empty($entity) || !isset($entity->blogCategoryDescription)) {
            return $this->_to('error.404');
        }

        $this->setBreadcrumb(['text' => $entity->blogCategoryDescription->title, 'href' => $entity->blogCategoryDescription->getUrlClient(), 'separator' => false]);

        $this->_processMetaSeo(
            '_buildForSeoByData',
            $entity->blogCategoryDescription->getMetaTitle(),
            $entity->blogCategoryDescription->getMetaDescription()
        );

        $this->_processRequest($id);

        $entities = $this->fetchRepository(BlogRepository::class)->getListForFrontend($this->getParams());

        return $this->render('client.infunstudio.blog.list', [
            'title' => $entity->blogCategoryDescription->title,
            'entity' => $entity,
            'entities' => $entities,
        ]);
    }

    protected function _buildDataCommon()
    {
        $this->setViewData([
            'blogCategories' => $this->getBlogCategories(),
            'blogTags' => $this->getBlogTags(),
            'products' => $this->getProductLatest(),
        ]);
    }

    protected function _processRequest($id)
    {
        if (!request()->has('category_id_eq')) {
            request()->merge([
                'category_id_eq' => $id
            ]);
        }
        request()->merge([
            'per_page' => request()->get('per_page') ?? getInfunStudioConfig('paginate.blog_category'),
        ]);
    }
}
