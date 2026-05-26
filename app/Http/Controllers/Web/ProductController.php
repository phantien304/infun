<?php

namespace App\Http\Controllers\Web;

use App\Data\Output\ProductDTO;
use App\Http\Controllers\Controller;
use App\Http\Supports\Pagination;
use App\Model\Entities\Ingredient;
use App\Model\Entities\Option;
use App\Model\Entities\Product;
use App\Model\Entities\ProductOption;
use App\Model\Entities\ProductRelated;
use App\Model\Entities\StoreReview;
use App\Model\Entities\UserWishlist;
use App\Repositories\Interfaces\BlogRepositoryInterface;
use App\Repositories\Interfaces\ProductSpecialRepositoryInterface;
use App\Repositories\Interfaces\ReviewRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    public function __construct(
        protected BlogRepositoryInterface $blogRepository,
        protected ProductSpecialRepositoryInterface $productSpecialRepository,
        protected ReviewRepositoryInterface $reviewRepository
    ) {
        $this->breadcrumbs = [
            ['text' => trans('messages.breadcrumbs.home'), 'href' => '/', 'separator' => false],
            ['text' => trans('messages.breadcrumbs.list_product'), 'href' => route('product.getList'), 'separator' => false],
        ];
    }

    public function index($id = '')
    {
        $tagCaches = [getCoreConfig('cache.product_root'), getCoreConfig('cache.products') . $id];
        $this->setOptions(['product_id' => $id]);

        $this->_updateView();

        $entity = $this->getEntityRedisCache($tagCaches, $id);
        if (empty($entity) || !isset($entity->productDescription)) {
            return $this->_to('error.404');
        }

        $this->setBreadcrumb(['text' => $entity->productDescription->name, 'href' => $entity->productDescription->getUrlClient(), 'separator' => false]);

        $this->_processMetaSeo('_buildForSeoByData', $entity->productDescription->getMetaTitle(), $entity->productDescription->getMetaDescription());

        $this->_processProductPrice($entity);

        $this->_processCategory($entity);

        list($options, $imageOptions) = $this->getProductOptionRedisCache($tagCaches, $entity->productOptions->toArray());

        return $this->render('client.infunstudio.product.index', [
            'ratingReview' => intval(round($entity->rating)),
            'entity' => $entity,
            'options' => $options,
            'wishlist' => $this->_getProductUserWishlist(),
            'images' => $this->_processProductImages($entity, $imageOptions),
            'related' => $this->getProductRelatedRedisCache($tagCaches),
            'storeReviews' => $this->_getStoreReviews(),
            'blogs' => $this->_getBlogLatest(),
        ]);
    }

    public function getList()
    {
        $this->processMetaSeo('buildForSeoBySetting', 'seo_title_products', 'seo_description_products');

        $entities = ProductDTO::collect($this->productRepo->list());

        return $this->render('web.product.list', [
            'entities' => $entities,
            'products' => $this->getProductLatest(),
        ]);
    }

    public function special()
    {
        $this->setBreadcrumb(['text' => trans('messages.breadcrumbs.special'), 'href' => route('product.special'), 'separator' => false]);
        $this->_processMetaSeo('_buildForSeoByConfig', 'product.special.title', 'product.special.description');

        $entities = $this->fetchRepository(ProductSpecialRepository::class)->getListForFrontend($this->getParams());

        $relation = [];
        if (request()->has('product_filter')) {
            $reqFilter = array_get(request()->get('product_filter', []), 'filter_value_id_in', []);
            $relation = [
                'productFilters' => function ($q) use ($reqFilter) {
                    $q->whereIn('filter_value_id', $reqFilter);
                },
                'productFilters.filterValue.filterValueDescription' => function ($q) {
                    $q->where('language_code', app()->getLocale());
                }
            ];
        }

        $productSpecials = $this->_buildDataProductSpecials($entities->pluck('product_id')->toArray(), $relation);

        return $this->render('client.infunstudio.product.special', [
            'productSpecials' => $productSpecials,
            'entities' => $entities,
            'products' => $this->getProductLatest(),
        ]);
    }

    protected function _processCategory(&$entity)
    {
        $categories = [];
        $productCategories = $entity->productCategories;
        if (count($productCategories)) {
            foreach ($productCategories as $item) {
                $categoryEntity = $item->category;
                if (isset($categoryEntity->categoryDescription)) {
                    if ($categoryEntity) {
                        $categories[] = [
                            'id' => $categoryEntity->id,
                            'slug' => $categoryEntity->categoryDescription->getUrlClient(),
                            'title' => $categoryEntity->categoryDescription->title
                        ];
                    }
                }
            }
        }
        $entity->categories = $categories;
    }

    public function getListReview($productId = 0)
    {
        $this->_processParamsForReviews($productId);
        $params = $this->getParams();
        $pageIndex = array_get($params, 'pageIndex', 1);
        $pageSize = array_get($params, 'pageSize');
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
                'color_emotion' => getInfunStudioConfig('emotion.background.' . $item['rating']),
                'text_emotion' => getInfunStudioConfig('emotion.text_emotion.' . $item['rating']),
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
        $reviewRepository = $this->fetchRepository(ReviewRepository::class);

        $query = $reviewRepository->where('product_id', array_get($params, 'product_id'))
            ->where('is_publish', 1)
            ->orderBy('created_at', 'DESC');

        $total = $reviewRepository->getTotalRowSearch($query);
        $data = $reviewRepository->getDataPagination($query);

        return [$total, $data];
    }

    protected function _updateView()
    {
        try {
            Product::where('id', $this->getOptions('product_id'))
                ->update(['viewed' => DB::raw('viewed+1')]);
        } catch (\Exception $e) {
            logError($e->getMessage());
        }
    }

    protected function _processProductPrice($entity)
    {
        $price = $entity->price;
        if (count($entity->productSpecials)) {
            $productSpecial = $entity->productSpecials->sortByDesc('priority')->first();
            $price = $productSpecial->price;
        }
        $this->setOptions(['price' => $price]);
    }

    public function getProductRelatedRedisCache($tag)
    {
        if (Cache::getDefaultDriver() == 'redis' && getConfigDb('config_redis_cache')) {
            $keyCache = $this->_keyCache . '_related';
            if (Cache::tags($tag)->has($keyCache)) {
                return Cache::tags($tag)->get($keyCache);
            }
            $related = $this->_getProductRelated();
            Cache::store('redis')->tags($tag)->put($keyCache, $related, getCoreConfig('time.cache'));
            return $related;
        }
        return $this->_getProductRelated();
    }

    protected function _getProductRelated()
    {
        $id = $this->getOptions('product_id');
        $relatedIds = ProductRelated::select('related_id')
            ->where('product_id', $id)
            ->whereNotIn('related_id', [$id])
            ->take(4)
            ->get();
        $relatedIds = $relatedIds->pluck('related_id')->toArray();
        $related = collect([]);
        if (filled($relatedIds)) {
            $related = $this->getRepository()->getProductRelated($relatedIds);
        }
        return $related;
    }

    protected function _getStoreReviews()
    {
        $id = $this->getOptions('product_id');
        return StoreReview::where('product_id', $id)
            ->take(16)
            ->get();
    }

    protected function _getBlogLatest()
    {
        return $this->fetchRepository(BlogRepository::class)->getBlogLatest(4);
    }

    protected function _getProductUserWishlist()
    {
        return UserWishlist::where('user_id', getUserLoginId())
            ->where('product_id', $this->getOptions('product_id'))
            ->first();
    }

    protected function _processProductImages($entity, $imageOptions)
    {
        $productImages = [];
        if (count($entity->productImages)) {
            foreach ($entity->productImages as $item) {
                $productImages[] = [
                    'key' => 'pImg' . $item->id,
                    'image' => $item->image,
                ];
            }
        }
        return array_merge([['key' => 'p' . $entity->id, 'image' => $entity->image]], $productImages, $imageOptions);
    }

    protected function _getProductIngredients($entity)
    {
        $ingredientIds = $entity->productIngredients->pluck('ingredient_id');
        $ingredients = collect([]);
        if (count($ingredientIds)) {
            $ingredients = Ingredient::whereIn('id', $ingredientIds)
                ->with([
                    'ingredientEffects.effect',
                    'ingredientSafeties.safety',
                    'ingredientSkincares.skincare',
                ])->get();
        }
        return $ingredients;
    }

    public function getProductOptionRedisCache($tag, $options)
    {
        if (Cache::getDefaultDriver() == 'redis' && getConfigDb('config_redis_cache')) {
            $keyCache = $this->_keyCache . '_option';
            if (Cache::tags($tag)->has($keyCache)) {
                return Cache::tags($tag)->get($keyCache);
            }
            list($data, $images) = $this->_buildProductOption($options);
            Cache::store('redis')->tags($tag)->put($keyCache, [$data, $images], getCoreConfig('time.cache'));
            return [$data, $images];
        }
        return $this->_buildProductOption($options);
    }

    protected function _buildProductOption($options)
    {
        $data = [];
        $images = [];
        foreach ($options as $index => $item) {
            $id = array_get($item, 'id');
            $optionId = array_get($item, 'option.id');
            $variation = Option::where('id', $optionId)->where('variation', 1)->exists() ? 1 : 2;
            $children = ProductOption::where('parent', $id)
                ->with([
                    'option.optionDescription' => function ($q) {
                        $q->where('language_code', app()->getLocale());
                    },
                    'option.optionValues' => function ($q) {
                        $q->orderBy('sort_order', 'ASC')
                            ->orderBy('id', 'ASC');
                    },
                    'option.optionValues.optionValueDescription'
                ])
                ->first();
            if (!empty($children)) {
                $children = $children->toArray();
                $optionChildValues = array_get($children, 'option.option_values');
                $optChildValue = [];
                foreach ($optionChildValues as $optChild) {
                    $optChildValue[] = [
                        'id' => $optChild['id'],
                        'value' => array_get($optChild, 'option_value_description.name'),
                        'image' => resizeImage($optChild['image'], 50, 50, 'client')
                    ];
                }
                $children = [
                    'id' => array_get($children, 'option.id'),
                    'name' => array_get($children, 'option.option_description.name'),
                    'name_display' => array_get($children, 'option.option_description.name_display'),
                    'type' => array_get($children, 'option.type'),
                    'variation' => array_get($children, 'option.variation'),
                    'option' => $optChildValue
                ];
            }
            $this->setOptions([
                'children' => $children,
                'option_type' => array_get($item, 'option.type'),
            ]);

            list($productOptionValues, $imageOption) = $this->_processProductOptionValues(array_get($item, 'product_option_values', []), $variation);
            $images = array_merge($images, $imageOption);

            $data[$index] = [
                'id' => $id,
                'value' => array_get($item, 'value'),
                'required' => array_get($item, 'required'),
                'option_id' => $optionId,
                'option_value_id' => [],
                'option_value_2_id' => [],
                'option_name' => array_get($item, 'option.option_description.name'),
                'option_type' => array_get($item, 'option.type'),
                'name_display' => array_get($item, 'option.option_description.name_display'),
                'variation' => $variation,
                'product_option_values' => $productOptionValues,
                'children' => $children
            ];
        }

        return [$data, $images];
    }

    protected function _processProductOptionValues($productOptionValues, $variation)
    {
        $data = [];
        $images = [];
        $optionType = $this->getOptions('option_type');

        foreach ($productOptionValues as $index => $optValue) {
            if ($optionType == 'image') {
                $images[] = [
                    'key' => 'opt' . $optValue['id'],
                    'image' => $optValue['image']
                ];
            }
            $optionValues = [
                'id' => $optValue['id'],
                'image' => $optValue['image'] ? resizeImage($optValue['image'], 1000, 1000, 'client') : '',
                'option_value_1_id' => $optValue['option_value_1_id'],
                'name' => array_get($optValue, 'option_value.option_value_description.name', ''),
                'product_id' => $optValue['product_id'],
                'product_option_id' => $optValue['product_option_id'],
                'product_option_values2' => $this->_processProductOptionValues2($optValue['product_option_values2']),
            ];
            if ($variation == array_key_last(getCoreConfig('variation'))) {
                $price = array_get($optValue, 'product_option_values2.0.price', '');
                $pricePrefix = array_get($optValue, 'product_option_values2.0.price_prefix', '');
                $optionPrice = ($pricePrefix == '+') ? +$price : -$price;

                $optionValues = array_merge($optionValues, [
                    'price' => $optionPrice,
                    'price_prefix' => $pricePrefix,
                    'quantity' => array_get($optValue, 'product_option_values2.0.quantity', ''),
                ]);
            }
            $data[$index] = $optionValues;
        }
        return [$data, $images];
    }

    protected function _processProductOptionValues2($productOptionValues2)
    {
        $optionValues2 = [];
        $children = $this->getOptions('children');
        $optionChildType = array_get($children, 'type');
        foreach ($productOptionValues2 as $i => $optValue2) {
            $optionPrice = 0;
            if ($optValue2['price_prefix'] == '+') {
                $optionPrice = +$optValue2['price'];
            }
            if ($optValue2['price_prefix'] == '-') {
                $optionPrice = -$optValue2['price'];
            }
            $optionValues2[$i] = [
                'id' => $optValue2['id'],
                'option_value_2_id' => $optValue2['option_value_2_id'],
                'name' => array_get($children, 'name'),
                'value' => array_get($optValue2, 'option_value.option_value_description.name', ''),
                'price' => $optionPrice,
                'price_prefix' => array_get($optValue2, 'price_prefix', ''),
                'quantity' => array_get($optValue2, 'quantity', 0),
                'type' => array_get($children, 'type', ''),
                'variation' => array_get($children, 'variation'),
            ];
            if ($optionChildType == 'image') {
                $optionValues2[$i]['image'] = resizeImage(array_get($optValue2, 'option_value.image', ''), 50, 50, 'client');
            }
        }
        return $optionValues2;
    }
}
