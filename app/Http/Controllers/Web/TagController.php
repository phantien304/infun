<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Repositories\Client\InfunStudio\BannerRepository;
use App\Repositories\Client\InfunStudio\BlogCategoryRepository;
use App\Repositories\Client\InfunStudio\BlogRepository;
use App\Repositories\Client\InfunStudio\BlogTagRepository;
use App\Repositories\Client\InfunStudio\ProductRepository;

class TagController extends Controller
{
    public function __construct(
        BlogTagRepository $blogTagRepository,
        BlogRepository $blogRepository,
        BannerRepository $bannerRepository,
        BlogCategoryRepository $blogCategoryRepository,
        ProductRepository $productRepository
    ) {
        parent::__construct();
        $this->setRepository($blogTagRepository);
        $this->registerRepository($blogRepository, $bannerRepository, $blogCategoryRepository, $productRepository);
        $this->_breadcrumbs = [
            ['text' => trans('messages.breadcrumbs.home'), 'href' => '/', 'separator' => false],
            ['text' => trans('messages.breadcrumbs.list_tag'), 'href' => route('tags.getList'), 'separator' => false],
        ];
    }

    public function index($id = '')
    {
        $entity = $this->getRepository()->getDetail($id);
        if (empty($entity) || !isset($entity->blogTagDescription)) {
            return $this->_to('error.404');
        }

        $this->setBreadcrumb(['text' => $entity->blogTagDescription->title, 'href' => $entity->blogTagDescription->getUrlClient(), 'separator' => false]);

        $this->_processMetaSeo(
            '_buildForSeoByData',
            $entity->blogTagDescription->getMetaTitle(),
            $entity->blogTagDescription->getMetaDescription()
        );

        request()->merge([
            'blog_description' => ['tag_cons' => $entity->blogTagDescription->title]
        ]);
        $entities = $this->fetchRepository(BlogRepository::class)->getListForFrontend($this->getParams());

        return $this->render('client.infunstudio.tag.index', [
            'title' => $entity->blogTagDescription->title,
            'entity' => $entity,
            'entities' => $entities
        ]);
    }

    public function getList()
    {
        $this->_processMetaSeo('_buildForSeoBySetting', 'seo_title_tags', 'seo_description_tags');

        request()->merge(['per_page' => 10000]);

        $entities = $this->getRepository()->getListForFrontend($this->getParams());

        return $this->render('client.infunstudio.tag.list', [
            'entities' => $entities
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
}
