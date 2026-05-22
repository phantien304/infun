<?php

namespace App\Data;

use App\Models\Entities\StoreReview;
use Illuminate\Support\Str;
use Spatie\LaravelData\Data;

class StoreReviewData extends Data
{
    public function __construct(
        public int $id,
        public int $product_id,
        public int $author_id,
        public string $urlWeb,
    ) {}
    public static function fromModel(StoreReview $storeReview): self
    {
        return new self(
            id: (int) $storeReview->id,
            product_id: (int) $storeReview->product_id,
            author_id: (int) $storeReview->author_id,
            urlWeb: self::buildUrl($storeReview),
        );
    }

    private static function buildUrl(StoreReview $storeReview, string $prefix = ''): string
    {
        $slug = Str::slug($storeReview->name)
            . '-' . getModuleConfig('url.store_review')
            . $storeReview->id;

        return $prefix === ''
            ? url('/' . $slug)
            : url('/' . $prefix . '/' . $slug);
    }
}
