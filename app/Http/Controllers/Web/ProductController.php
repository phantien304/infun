<?php

namespace App\Http\Controllers\Web;

use App\Data\Output\BlogDTO;
use App\Data\Output\ProductDTO;
use App\Data\Output\ReviewCriteriaDTO;
use App\Data\Output\ReviewTagDTO;
use App\Data\Output\StoreReviewDTO;
use App\Http\Controllers\Controller;
use App\Models\Entities\UserWishlist;
use App\Repositories\Interfaces\BlogRepositoryInterface;
use App\Repositories\Interfaces\ReviewRepositoryInterface;
use App\Repositories\Interfaces\StoreReviewRepositoryInterface;
use App\Repositories\Interfaces\UserWishlistRepositoryInterface;
use App\Services\ProductOptionService;
use App\Services\Reward\RewardEarnService;

class ProductController extends Controller
{
    public function __construct(
        protected BlogRepositoryInterface $blogRepo,
        protected ReviewRepositoryInterface $reviewRepo,
        protected StoreReviewRepositoryInterface $storeReviewRepo,
        protected ProductOptionService $productOptionService,
        protected RewardEarnService $rewardEarn,
    ) {
        $this->breadcrumbs = [
            ['text' => trans('messages.breadcrumbs.home'), 'href' => '/', 'separator' => false],
            ['text' => trans('messages.breadcrumbs.list_product'), 'href' => route('product.getList'), 'separator' => false],
        ];
    }

    protected function lazyMap(): array
    {
        return array_merge(parent::lazyMap(), [
            'userWishlistRepo' => UserWishlistRepositoryInterface::class,
        ]);
    }

    public function index($id = '')
    {
        $id = (int) $id;
        $entity = $this->productRepo->getProductDetail($id);
        if (! $entity || ! $entity->description) {
            return $this->toUrl('error.404');
        }

        $this->productRepo->incrementViewed($id);

        $product = ProductDTO::from($entity);
        $this->setBreadcrumb(['text' => $product->name, 'href' => $product->url, 'separator' => false]);
        $this->processMetaSeo('buildForSeoByData', (string) $product->metaTitle(), (string) $product->metaDescription());

        $optionTree = $this->productOptionService->buildOptions($entity);
        $reviewData = [];
        if ($entity->is_review) {
            $userId = (int) getCurrentUserId();
            $reviewData = [
                'reviews'                => null,
                'reviewCriteria'         => ReviewCriteriaDTO::collect($this->reviewRepo->getActiveCriteria()),
                'reviewTags'             => ReviewTagDTO::collect($this->reviewRepo->getActiveTags()),
                'reviewCriteriaAverages' => $this->reviewRepo->getCriteriaAverages($id),
                'hasReviewed'            => $userId > 0
                    ? $this->reviewRepo->hasReviewedFromOrder($userId, $id)
                    : false,
                'reviewPolicy'           => setting('config_review_policy', getCoreConfig('review.default_policy')),
                'hasVerifiedPurchase'    => $userId > 0
                    ? (bool) $this->reviewRepo->findVerifiedOrderId($userId, $id)
                    : false,
            ];
        }

        return $this->render('web::product.index', [
            'entity'             => $product,
            'rewardEarn'         => $this->rewardEarn->perUnit($entity, (int) $entity->price),
            'productOptions'     => $optionTree['optionGroups'],
            'variantMatrix'      => $optionTree['variantMatrix'],
            'defaultVariant'     => $optionTree['defaultVariant'],
            'variantGallery'     => $optionTree['variantGallery'],
            'productImages'      => array_merge($product->gallery, $optionTree['variantSwatchImages']),
            'userWishlist'       => $this->getProductUserWishlist($id),
            'relatedProducts'    => ProductDTO::collect($this->productRepo->getProductRelatedByProductId($id)),
            'storeReviews'       => StoreReviewDTO::collect($this->storeReviewRepo->getStoreReviewsByProduct($id)),
            'latestBlogs'        => BlogDTO::collect($this->blogRepo->getBlogLatest(4)),
        ] + $reviewData);
    }

    public function getList()
    {
        $this->processMetaSeo('buildForSeoBySetting', 'seo_title_products', 'seo_description_products');
        $productList = $this->productRepo->list();
        $productList->setCollection(
            $productList->getCollection()->map(fn ($m) => ProductDTO::from($m))
        );

        return $this->render('web::product.list', [
            'entities'      => $productList,
            'latestProducts' => ProductDTO::collect($this->getProductLatest()),
            'sortMenu'      => $this->productRepo->getSortMenu(),
            'perPageMenu'   => $this->productRepo->getPerPageMenu(),
        ]);
    }

    public function special()
    {
        $this->setBreadcrumb(['text' => trans('messages.breadcrumbs.special'), 'href' => route('product.special'), 'separator' => false]);
        $this->processMetaSeo('buildForSeoByConfig', 'product.special.title', 'product.special.description');

        $productListSpecial = $this->productRepo->getListSpecial();
        $productListSpecial->setCollection(
            $productListSpecial->getCollection()->map(fn ($m) => ProductDTO::from($m))
        );

        return $this->render('web::product.special', [
            'entities'      => $productListSpecial,
            'latestProducts' => ProductDTO::collect($this->getProductLatest()),
            'sortMenu'      => $this->productRepo->getSortMenu(),
            'perPageMenu'   => $this->productRepo->getPerPageMenu(),
        ]);
    }

    protected function getProductUserWishlist(int $productId): ?UserWishlist
    {
        $userId = getCurrentUserId();
        if (! $userId) {
            return null;
        }
        return $this->userWishlistRepo->findForUser($userId, $productId);
    }
}
