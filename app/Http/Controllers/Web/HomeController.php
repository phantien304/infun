<?php

namespace App\Http\Controllers\Web;

use App\Data\Output\BlogDTO;
use App\Data\Output\CategoryDTO;
use App\Data\Output\ProductDTO;
use App\Data\Output\StoreReviewDTO;
use App\Helpers\ThemeManager;
use App\Http\Controllers\Controller;
use App\Repositories\Interfaces\BannerRepositoryInterface;
use App\Repositories\Interfaces\BlogRepositoryInterface;
use App\Repositories\Interfaces\CategoryRepositoryInterface;
use App\Repositories\Interfaces\ProductRepositoryInterface;
use App\Repositories\Interfaces\StoreReviewRepositoryInterface;

class HomeController extends Controller
{
    public function __construct(
        protected BannerRepositoryInterface $bannerRepo,
        protected BlogRepositoryInterface $blogRepo,
        protected ProductRepositoryInterface $productRepo,
        protected StoreReviewRepositoryInterface $storeReviewRepo,
        protected CategoryRepositoryInterface $categoryRepo
    ) {
    }

    public function index($slug = '')
    {
        if (empty($slug)) {
            $this->processMetaSeo('buildForSeoBySetting', 'seo_title_home', 'seo_description_home');

            $data = [
                'banners' => $this->bannerRepo->getBannerByPage('home', 'top', 3, ThemeManager::current()),
                'blogs' => BlogDTO::collect($this->blogRepo->getBlogLatest(4)),
                'storeReviews' => StoreReviewDTO::collect($this->storeReviewRepo->getStoreReviewsFeatured()),
                'features' => ProductDTO::collect($this->productRepo->getProductFeature()),
            ];

            foreach ($this->homeThemeBlocks() as $block => $resolve) {
                if (ThemeManager::wants($block)) {
                    $data = array_merge($data, $resolve());
                }
            }

            return $this->render('web::page.home', $data);
        }
        list($controllerClass, $id) = $this->getControllerBySlug($slug);
        if (!class_exists($controllerClass) || str_contains($controllerClass, 'ErrorController')) {
            return $this->toUrl(url: 'error.404');
        }
        return $this->forward($controllerClass, 'index', ['id' => $id]);
    }

    protected function homeThemeBlocks(): array
    {
        return [
            'categories' => fn () => [
                'homeCategories' => CategoryDTO::collect(
                    $this->categoryRepo->listAllCached()
                        ->where('parent_id', 0)
                        ->sortBy('sort_order')
                        ->take(8)
                        ->values()
                ),
            ],
            'flash_sale' => fn () => [
                'flashSaleProducts' => ProductDTO::collect(
                    $this->productRepo->getProductVariantSpecialLatest(10)
                ),
            ],
            'latest' => fn () => [
                'latestProducts' => ProductDTO::collect(
                    $this->productRepo->getProductLatest(10)
                ),
            ],
        ];
    }
}
