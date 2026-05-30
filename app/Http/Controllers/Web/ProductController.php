<?php

namespace App\Http\Controllers\Web;

use App\Data\Output\BlogDTO;
use App\Data\Output\ProductDTO;
use App\Data\Output\StoreReviewDTO;
use App\Http\Controllers\Controller;
use App\Http\Supports\Pagination;
use App\Models\Entities\Ingredient;
use App\Models\Entities\UserWishlist;
use App\Repositories\Interfaces\BlogRepositoryInterface;
use App\Repositories\Interfaces\ReviewRepositoryInterface;
use App\Repositories\Interfaces\StoreReviewRepositoryInterface;
use App\Services\ProductOptionService;
use Carbon\Carbon;

class ProductController extends Controller
{
    public function __construct(
        protected BlogRepositoryInterface $blogRepo,
        protected ReviewRepositoryInterface $reviewRepo,
        protected StoreReviewRepositoryInterface $storeReviewRepo,
        protected ProductOptionService $optionService,
    ) {
        $this->breadcrumbs = [
            ['text' => trans('messages.breadcrumbs.home'), 'href' => '/', 'separator' => false],
            ['text' => trans('messages.breadcrumbs.list_product'), 'href' => route('product.getList'), 'separator' => false],
        ];
    }

    /**
     * Trang chi tiết 1 sản phẩm. Routing đi qua HomeController::index(slug) →
     * getControllerBySlug() forward về đây với $id parse từ slug.
     *
     * Pattern mới:
     *  - Data + cache nằm ở repo (getProductDetail)
     *  - Increment view tách thành 1 call repo nhỏ
     *  - Option tree build bằng ProductOptionService (stateless)
     *  - Blade nhận DTO, không nhận Model
     */
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
        $this->processMetaSeo('buildForSeoByData', (string) $product->metaTitle, (string) $product->metaDescription);

        [$options, $imageOptions] = $this->optionService->build($entity->productOptions ?? collect());

        return $this->render('web.product.index', [
            'entity'       => $entity,
            'product'      => $product,
            'options'      => $options,
            'images'       => array_merge($product->gallery, $imageOptions),
            'wishlist'     => $this->getProductUserWishlist($id),
            'related'      => ProductDTO::collect($this->productRepo->getProductRelatedByProductId($id)),
            'storeReviews' => StoreReviewDTO::collect($this->storeReviewRepo->getStoreReviewsByProduct($id)),
            'blogs'        => BlogDTO::collect($this->blogRepo->getBlogLatest(4)),
        ]);
    }

    public function getList()
    {
        $this->processMetaSeo('buildForSeoBySetting', 'seo_title_products', 'seo_description_products');

        $entities = $this->productRepo->list();
        $entities->setCollection(
            $entities->getCollection()->map(fn ($m) => ProductDTO::from($m))
        );

        return $this->render('web.product.list', [
            'entities'    => $entities,
            'products'    => ProductDTO::collect($this->getProductLatest()),
            'sortMenu'    => $this->productRepo->getSortMenu(),
            'perPageMenu' => $this->productRepo->getPerPageMenu(),
        ]);
    }

    public function special()
    {
        $this->setBreadcrumb(['text' => trans('messages.breadcrumbs.special'), 'href' => route('product.special'), 'separator' => false]);
        $this->processMetaSeo('buildForSeoByConfig', 'product.special.title', 'product.special.description');

        $entities = $this->productRepo->getListSpecial();
        $entities->setCollection(
            $entities->getCollection()->map(fn ($m) => ProductDTO::from($m))
        );

        return $this->render('web.product.special', [
            'entities'    => $entities,
            'products'    => ProductDTO::collect($this->getProductLatest()),
            'sortMenu'    => $this->productRepo->getSortMenu(),
            'perPageMenu' => $this->productRepo->getPerPageMenu(),
        ]);
    }

    protected function getProductUserWishlist(int $productId): ?UserWishlist
    {
        $userId = getCurrentUserId();
        if (! $userId) {
            return null;
        }
        return UserWishlist::where('user_id', $userId)
            ->where('product_id', $productId)
            ->first();
    }

    public function getListReview($productId = 0)
    {
        $this->_processParamsForReviews($productId);
        $params = $this->getParams();
        $pageIndex = data_get($params, 'pageIndex', 1);
        $pageSize = data_get($params, 'pageSize');
        $productId = request()->get('product_id') ?? 1;

        list($total, $data) = $this->_getReviews();
        $reviews = $this->_processDataReviews($data);

        $pagination = new Pagination();
        $pagination->total = $total;
        $pagination->pageIndex = $pageIndex;
        $pagination->pageSize = $pageSize;
        $pagination->text = 'Hiển thị {start} đến {end} trong {total} ({pages} Trang)';
        $pagination->url = route('product.getListReview', ['product_id' => $productId, 'pageIndex' => '_page']);

        $pagination = $pagination->render();
        return $this->_buildTemplateReview($reviews, $pagination);
    }

    protected function _processParamsForReviews($productId)
    {
        request()->merge(['pageSize' => 5]);
        if ($productId != 0) {
            request()->merge(['product_id' => $productId]);
        }
    }

    protected function _processDataReviews($data)
    {
        $reviews = [];
        foreach ($data as $item) {
            $reviews[] = [
                'author' => $item['author'],
                'text' => html_entity_decode($item['text'], ENT_QUOTES, 'UTF-8'),
                'rating' => (int)$item['rating'],
                'emotion' => '<img src="/client/images/rate_' . $item['rating'] . '.svg" style="min-width: 14px;vertical-align: text-top;"/>',
                'color_emotion' => getModuleConfig('emotion.background.' . $item['rating']),
                'text_emotion' => getModuleConfig('emotion.text_emotion.' . $item['rating']),
                'created_at' => Carbon::parse($item['created_at'])->diffForHumans()
            ];
        }
        return $reviews;
    }

    protected function _buildTemplateReview($reviews, $pagination)
    {
        $output = '';
        if ($reviews) {
            foreach ($reviews as $review) {
                $output .= "<div class='single-comment justify-content-between d-flex mb-30'>
                                <div class='user justify-content-between d-flex'>
                                    <div class='thumb text-center'>
                                        <img src='/client/images/ic_placeholder_avatar.png'/>
                                        <a class='font-heading text-brand'>{$review['author']}</a>
                                    </div>
                                    <div class='desc'>
                                        <div class='created font-xs text-muted'>{$review['created_at']}</div>
                                        <div class='emotion'>
                                            <span>{$review['emotion']}</span>&nbsp;
                                            <span style='color: {$review['color_emotion']}'><b>{$review['text_emotion']}</b></span>
                                            &nbsp;<img src='/client/images/stars-{$review['rating']}.png' style='height: 16px;vertical-align: text-bottom;' alt='{$review['author']}'/>
                                        </div>
                                        <div class='text mt-2 mb-10'>{$review['text']}</div>
                                    </div>
                                </div>
                            </div>";
            }
            $output .= "{$pagination}";
        } else {
            $output .= "<div class='content' >Không có đánh giá cho sản phẩm này.</div >";
        }
        return $output;
    }

    protected function _getReviews()
    {
        $params = $this->getParams();

        $query = $this->reviewRepo->where('product_id', array_get($params, 'product_id'))
            ->where('is_publish', 1)
            ->orderBy('created_at', 'DESC');

        $total = $this->reviewRepo->getTotalRowSearch($query);
        $data = $this->reviewRepo->getDataPagination($query);

        return [$total, $data];
    }

    protected function _getProductIngredients($entity)
    {
        $ingredientIds = $entity->productIngredients->pluck('ingredient_id');
        if ($ingredientIds->isEmpty()) {
            return collect();
        }
        return Ingredient::whereIn('id', $ingredientIds)
            ->with([
                'ingredientEffects.effect',
                'ingredientSafeties.safety',
                'ingredientSkincares.skincare',
            ])->get();
    }
}
