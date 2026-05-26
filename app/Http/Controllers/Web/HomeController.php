<?php

namespace App\Http\Controllers\Web;

use App\Data\Output\BlogDTO;
use App\Data\Output\ProductDTO;
use App\Data\Output\StoreReviewDTO;
use App\Http\Controllers\Controller;
use App\Repositories\Interfaces\BannerRepositoryInterface;
use App\Repositories\Interfaces\BlogRepositoryInterface;
use App\Repositories\Interfaces\ProductRepositoryInterface;
use App\Repositories\Interfaces\ProductSpecialRepositoryInterface;
use App\Repositories\Interfaces\StoreReviewRepositoryInterface;

class HomeController extends Controller
{
    public function __construct(
        protected BannerRepositoryInterface $bannerRepo,
        protected BlogRepositoryInterface $blogRepo,
        protected ProductRepositoryInterface $productRepo,
        protected ProductSpecialRepositoryInterface $productSpecialRepo,
        protected StoreReviewRepositoryInterface $storeReviewRepo
    ) {}
    public function index($slug = '')
    {
        if (empty($slug)) {
            $this->processMetaSeo('buildForSeoBySetting', 'seo_title_home', 'seo_description_home');
            return $this->render('web.page.home', [
                'banners' => $this->bannerRepo->getBannerByPage('home', 'top'),
                'blogs' => BlogDTO::collect($this->blogRepo->getBlogLatest(4)),
                'storeReviews' => StoreReviewDTO::collect($this->storeReviewRepo->getStoreReviewsFeatured()),
                'features' => ProductDTO::collect($this->productRepo->getProductFeature()),
            ]);
        }
        list($controllerClass, $id) = $this->getControllerBySlug($slug);
        if (!class_exists($controllerClass) || str_contains($controllerClass, 'ErrorController')) {
            return $this->toUrl(url: 'error.404');
        }
        return $this->forward($controllerClass, 'index', ['id' => $id]);
    }
}
