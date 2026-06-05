<?php

namespace App\Data\Output;

use App\Models\Entities\Review;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;

/**
 * Output DTO cho UI review section (Shopee-style).
 *
 * Nguồn dữ liệu:
 *   - Bảng review (root) + relations eager-load: reviewRatings, media, replies, tags.
 *   - myVote / hasMedia / hasText resolve qua param thứ 2 fromModel (user
 *     hiện tại) — tránh N+1 query khi render list.
 *
 * Convention DTO trong CLAUDE.md:
 *   - Property camelCase (reviewCount, helpfulCount).
 *   - Collection con: Illuminate\Support\Collection + #[DataCollectionOf].
 *   - Date xuất ra blade: Carbon raw (blade tự ->format khi hiển thị).
 */
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
        #[DataCollectionOf(ReviewRatingDTO::class)]
        public Collection $ratings,
        #[DataCollectionOf(ReviewMediaDTO::class)]
        public Collection $media,
        #[DataCollectionOf(ReviewReplyDTO::class)]
        public Collection $replies,
        #[DataCollectionOf(ReviewTagDTO::class)]
        public Collection $tags,
    ) {
    }

    public static function fromModel(Review $r, ?int $currentUserId = null): self
    {
        $user        = $r->user;
        $displayName = (string) ($user->full_name ?? $r->author ?? 'Khách');
        $avatar      = $user?->avatar ? asset($user->avatar) : null;

        // Variant label cho UX "Phân loại: Size M / Đỏ".
        $variantLabel = null;
        if ($r->relationLoaded('productVariant') && $r->productVariant) {
            $v = $r->productVariant;
            $variantLabel = (string) ($v->description?->name ?? $v->sku ?? '');
            if ($variantLabel === '') {
                $variantLabel = null;
            }
        }

        // My vote: cần query helpfuls đã eager-load + filter user hiện tại.
        $myVote = 0;
        if ($currentUserId && $r->relationLoaded('reviewHelpfuls')) {
            $mine = $r->reviewHelpfuls->firstWhere('user_id', $currentUserId);
            $myVote = $mine ? (int) $mine->vote_type : 0;
        }

        return new self(
            id:               (int) $r->id,
            productId:        (int) $r->product_id,
            productVariantId: $r->product_variant_id ? (int) $r->product_variant_id : null,
            orderId:          $r->order_id ? (int) $r->order_id : null,
            userId:           $r->user_id ? (int) $r->user_id : null,
            author:           (string) ($r->author ?? ''),
            displayName:      $displayName,
            userAvatar:       $avatar,
            title:            $r->title,
            text:             (string) ($r->text ?? ''),
            rating:           (int) $r->rating,
            status:           (int) $r->status,
            isAnonymous:      (bool) $r->is_anonymous,
            variantLabel:     $variantLabel,
            source:           (string) ($r->source ?? 'web'),
            helpfulCount:     (int) $r->helpful_count,
            unhelpfulCount:   (int) $r->unhelpful_count,
            replyCount:       (int) $r->reply_count,
            mediaCount:       (int) $r->media_count,
            myVote:           $myVote,
            createdAt:        $r->created_at,
            createdAtHuman:   $r->created_at?->diffForHumans(),
            ratings:  ReviewRatingDTO::collect($r->relationLoaded('reviewRatings') ? $r->reviewRatings : collect()),
            media:    ReviewMediaDTO::collect($r->relationLoaded('reviewMedia') ? $r->reviewMedia : collect()),
            replies:  ReviewReplyDTO::collect($r->relationLoaded('reviewReplies') ? $r->reviewReplies : collect()),
            tags:     ReviewTagDTO::collect($r->relationLoaded('reviewTags') ? $r->reviewTags : collect()),
        );
    }
}
