<?php

namespace App\Data\Output;

use App\Models\Entities\Review;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;

class ReviewDTO extends Data
{
    public function __construct(
        public int $id,
        public int $productId,
        public ?int $productVariantId,
        public ?int $orderId,
        public ?int $userId,
        public string $author,
        public string $displayName,
        public ?string $userAvatar,
        public ?string $title,
        public string $text,
        public int $rating,
        public int $status,
        public bool $isAnonymous,
        public ?string $variantLabel,
        public string $source,
        public int $helpfulCount,
        public int $unhelpfulCount,
        public int $replyCount,
        public int $mediaCount,
        public int $myVote,
        public ?Carbon $createdAt,
        public ?string $createdAtHuman,
        public Collection $ratings,
        public Collection $media,
        public Collection $replies,
        public Collection $tags,
    ) {
    }

    public static function fromModel(Review $review, ?int $currentUserId = null): self
    {
        $user        = $review->user;
        $displayName = (string) ($user->full_name ?? $review->author ?? 'Khách');
        $avatar      = $user?->avatar ? asset($user->avatar) : null;

        $variantLabel = null;
        if ($review->relationLoaded('productVariant') && $review->productVariant) {
            $variant = $review->productVariant;
            $variantLabel = (string) ($variant->description?->name ?? $variant->sku ?? '');
            if ($variantLabel === '') {
                $variantLabel = null;
            }
        }

        $myVote = 0;
        if ($currentUserId && $review->relationLoaded('reviewHelpfuls')) {
            $mine = $review->reviewHelpfuls->firstWhere('user_id', $currentUserId);
            $myVote = $mine ? (int) $mine->vote_type : 0;
        }

        return new self(
            id:               (int) $review->id,
            productId:        (int) $review->product_id,
            productVariantId: $review->product_variant_id ? (int) $review->product_variant_id : null,
            orderId:          $review->order_id ? (int) $review->order_id : null,
            userId:           $review->user_id ? (int) $review->user_id : null,
            author:           (string) ($review->author ?? ''),
            displayName:      $displayName,
            userAvatar:       $avatar,
            title:            $review->title,
            text:             (string) ($review->text ?? ''),
            rating:           (int) $review->rating,
            status:           (int) $review->status,
            isAnonymous:      (bool) $review->is_anonymous,
            variantLabel:     $variantLabel,
            source:           (string) ($review->source ?? 'web'),
            helpfulCount:     (int) $review->helpful_count,
            unhelpfulCount:   (int) $review->unhelpful_count,
            replyCount:       (int) $review->reply_count,
            mediaCount:       (int) $review->media_count,
            myVote:           $myVote,
            createdAt:        $review->created_at,
            createdAtHuman:   $review->created_at?->diffForHumans(),
            ratings:  ReviewRatingDTO::collect($review->relationLoaded('reviewRatings') ? $review->reviewRatings : collect()),
            media:    ReviewMediaDTO::collect($review->relationLoaded('reviewMedia') ? $review->reviewMedia : collect()),
            replies:  ReviewReplyDTO::collect($review->relationLoaded('reviewReplies') ? $review->reviewReplies : collect()),
            tags:     ReviewTagDTO::collect($review->relationLoaded('reviewTags') ? $review->reviewTags : collect()),
        );
    }
}
