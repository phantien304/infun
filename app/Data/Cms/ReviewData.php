<?php

namespace App\Data\Cms;

use App\Models\Entities\Review;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Data;

class ReviewData extends Data
{
    public function __construct(
        public int $id,
        public int $product_id,
        public ?string $product_name,
        public ?int $product_variant_id,
        public ?int $order_id,
        public ?int $user_id,
        public ?string $author,
        public ?string $email,
        public ?string $title,
        public ?string $text,
        public int $rating,
        public int $status,
        public bool $is_publish,
        public bool $is_anonymous,
        public string $language_code,
        public string $source,
        public int $helpful_count,
        public int $unhelpful_count,
        public int $reply_count,
        public int $media_count,
        public int $reports_count,
        public ?string $approved_at,
        public ?int $approved_by,
        public ?string $created_at,
        public ?string $deleted_at,
        public Collection $ratings,
        public Collection $media,
        public Collection $replies,
        public Collection $tags,
        public Collection $reports,
    ) {
    }

    public static function fromModel(Review $review): self
    {
        return new self(
            id: (int) $review->id,
            product_id: (int) $review->product_id,
            product_name: $review->relationLoaded('product')
                ? ($review->product?->description?->name ?? $review->product?->name)
                : null,
            product_variant_id: $review->product_variant_id ? (int) $review->product_variant_id : null,
            order_id: $review->order_id ? (int) $review->order_id : null,
            user_id: $review->user_id ? (int) $review->user_id : null,
            author: $review->author,
            email: $review->email,
            title: $review->title,
            text: $review->text,
            rating: (int) $review->rating,
            status: (int) $review->status,
            is_publish: (bool) $review->is_publish,
            is_anonymous: (bool) $review->is_anonymous,
            language_code: (string) $review->language_code,
            source: (string) $review->source,
            helpful_count: (int) $review->helpful_count,
            unhelpful_count: (int) $review->unhelpful_count,
            reply_count: (int) $review->reply_count,
            media_count: (int) $review->media_count,
            reports_count: (int) ($review->review_reports_count ?? ($review->relationLoaded('reviewReports') ? $review->reviewReports->count() : 0)),
            approved_at: $review->approved_at?->toDateTimeString(),
            approved_by: $review->approved_by !== null ? (int) $review->approved_by : null,
            created_at: $review->created_at?->toDateTimeString(),
            deleted_at: $review->deleted_at?->toDateTimeString(),
            ratings: $review->relationLoaded('reviewRatings')
                ? ReviewRatingItemData::collect($review->reviewRatings, Collection::class)
                : collect(),
            media: $review->relationLoaded('reviewMedia')
                ? ReviewMediaItemData::collect($review->reviewMedia, Collection::class)
                : collect(),
            replies: $review->relationLoaded('reviewReplies')
                ? ReviewReplyItemData::collect($review->reviewReplies, Collection::class)
                : collect(),
            tags: $review->relationLoaded('reviewTags')
                ? ReviewTagData::collect($review->reviewTags, Collection::class)
                : collect(),
            reports: $review->relationLoaded('reviewReports')
                ? ReviewReportItemData::collect($review->reviewReports, Collection::class)
                : collect(),
        );
    }
}
