<?php

namespace App\Data\Cms;

use App\Models\Entities\StoreReview;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Data;

/**
 * Cột thật của bảng `store_review` (đối chiếu mt219/database/infunstudio.sql,
 * KHÔNG có meta_title/meta_description ở đây — 2 field đó nằm ở
 * store_review_description theo từng ngôn ngữ, giống Category):
 * id, viewed, product_id, name, image, social_icon, featured, author_id,
 * created_at, updated_at, deleted_at.
 */
class StoreReviewData extends Data
{
    public function __construct(
        public int $id,
        public int $viewed,
        public ?int $product_id,
        public ?string $name,
        public ?string $image,
        public ?string $social_icon,
        public int $featured,
        public ?int $author_id,
        public ?string $title,
        public ?string $deleted_at,
        public Collection $store_review_descriptions,
    ) {
    }

    public static function fromModel(StoreReview $storeReview): self
    {
        // listForCms() KHÔNG eager-load 'descriptions' (dùng leftJoin +
        // select('store_review_description.title') để phân trang hiệu quả
        // thay vì N+1 load hết descriptions của từng dòng) — cột `title` join
        // được Eloquent hydrate thẳng vào $attributes của model, đọc qua
        // getAttribute() trước. getForCms()/getDetail() (trang show/edit)
        // thì eager-load 'descriptions' và KHÔNG có cột title join này, nên
        // fallback sang relationship như cũ. Thiếu bước ưu tiên attribute
        // join này khiến cột Title ở trang danh sách luôn hiển thị trống dù
        // dữ liệu descriptions vẫn tồn tại (chỉ show()/form.jsx đọc đúng).
        $title = $storeReview->getAttribute('title')
            ?? ($storeReview->relationLoaded('descriptions')
                ? $storeReview->descriptions->first()?->title
                : null);

        return new self(
            id: (int) $storeReview->id,
            viewed: (int) $storeReview->viewed,
            product_id: $storeReview->product_id !== null ? (int) $storeReview->product_id : null,
            name: $storeReview->name,
            image: $storeReview->image,
            social_icon: $storeReview->social_icon,
            featured: (int) $storeReview->featured,
            author_id: $storeReview->author_id !== null ? (int) $storeReview->author_id : null,
            title: $title,
            deleted_at: $storeReview->deleted_at?->toDateTimeString(),
            store_review_descriptions: $storeReview->relationLoaded('descriptions')
                ? StoreReviewDescriptionData::collect($storeReview->descriptions, Collection::class)
                : collect(),
        );
    }
}
