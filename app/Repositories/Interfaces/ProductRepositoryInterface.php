<?php

namespace App\Repositories\Interfaces;

use App\Models\Entities\Product;
use App\Repositories\Base\BaseRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

interface ProductRepositoryInterface extends BaseRepositoryInterface
{
    public function getByIds(array $productIds);

    public function getProductDetail(int $id): ?Product;

    /**
     * Lấy product theo id, BẮT BUỘC is_review = 1 (admin cho phép user
     * gửi đánh giá). Dùng cho luồng review (ReviewController::saveReview).
     * Trả null nếu product không tồn tại hoặc admin tắt review.
     */
    public function findReviewableProduct(int $id): ?Product;

    /**
     * Tìm product hợp lệ để thêm vào giỏ (tồn tại + is_add_cart + dateAvailable),
     * eager-load description. Dùng cho CheckoutController::addToCart. Trả null
     * nếu không thoả.
     */
    public function findAddableToCart(int $id): ?Product;

    public function incrementViewed(int $id): void;

    public function getProductRelatedByProductId(int $id, int $limit = 4);

    public function getProductFeature(int $limit = 6);

    public function getProductLatest(int $limit = 6);

    public function getProductRelated(array $productIds);

    public function getListSpecial(?Request $request = null): LengthAwarePaginator;

    public function getProductVariantSpecialLatest(int $limit = 8);

    public function getSortMenu(): array;

    public function getPerPageMenu(): array;
}
