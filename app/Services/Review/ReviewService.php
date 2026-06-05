<?php

namespace App\Services\Review;

use App\Helpers\Facades\MyStorage;
use App\Models\Entities\Review;
use App\Models\Entities\ReviewCriteria;
use App\Models\Entities\ReviewHelpful;
use App\Models\Entities\ReviewMedia;
use App\Models\Entities\ReviewReport;
use App\Models\Entities\ReviewTag;
use App\Repositories\Interfaces\ReviewRepositoryInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReviewService
{
    public function __construct(
        protected ReviewRepositoryInterface $reviewRepo,
    ) {
    }

    public function submitReview(array $data): Review
    {
        $productId    = (int) $data['product_id'];
        $userId       = (int) ($data['user_id'] ?? getCurrentUserId());
        $isAuthed     = $userId > 0;

        $orderId = $isAuthed
            ? $this->reviewRepo->findVerifiedOrderId($userId, $productId)
            : null;

        $policy = setting('config_review_policy', getCoreConfig('review.default_policy'));

        if ($policy === getCoreConfig('review.policy.login') && ! $isAuthed) {
            throw new \DomainException(trans('messages.review.login_required'));
        }
        if ($policy === getCoreConfig('review.policy.purchase')) {
            if (! $isAuthed) {
                throw new \DomainException(trans('messages.review.login_required'));
            }
            if (! $orderId) {
                throw new \DomainException(trans('messages.review.purchase_required'));
            }
        }
        if ($orderId && $this->reviewRepo->hasReviewedFromOrder($userId, $productId)) {
            throw new \DomainException(trans('messages.review.already_submitted'));
        }

        return DB::transaction(function () use ($data, $productId, $userId, $orderId) {
            $ratingInput = $data['rating'] ?? null;
            $isMultiCriteria = is_array($ratingInput);
            $overallRating = $isMultiCriteria
                ? (int) round(collect($ratingInput)->avg() ?: 0)
                : (int) $ratingInput;

            $overallRating = max(1, min(5, $overallRating));

            $review = Review::create([
                'product_id'         => $productId,
                'product_variant_id' => $data['product_variant_id'] ?? null,
                'order_id'           => $orderId,
                'user_id'            => $userId,
                'ip'                 => $data['ip'] ?? getIpVisitor(),
                'author'             => $data['author'] ?? ($data['display_name'] ?? ''),
                'email'              => $data['email'] ?? null,
                'title'              => $data['title'] ?? null,
                'text'               => $data['text'] ?? '',
                'rating'             => $overallRating,
                'status'             => getCoreConfig('review.status.pending'),
                'is_publish'         => 0,
                'is_anonymous'       => ! empty($data['is_anonymous']),
                'language_code'      => app()->getLocale(),
                'source'             => $data['source'] ?? 'web',
                'user_agent'         => substr((string) request()->userAgent(), 0, 255),
            ]);

            if ($isMultiCriteria) {
                $this->attachCriteriaRatings($review->id, $ratingInput);
            }

            if (! empty($data['tags']) && is_array($data['tags'])) {
                $this->attachTags($review->id, $data['tags']);
            }

            if (! empty($data['media'])) {
                $this->attachMedia($review, $data['media']);
            }

            $review->refresh();

            return $review;
        });
    }

    protected function attachCriteriaRatings(int $reviewId, array $ratingByCode): void
    {
        $codes = array_keys($ratingByCode);
        $criteriaMap = ReviewCriteria::active()
            ->whereIn('code', $codes)
            ->pluck('id', 'code');

        $rows = [];
        $now = now();
        foreach ($ratingByCode as $code => $value) {
            $value = (int) $value;
            if ($value < 1 || $value > 5) {
                continue;
            }
            $id = $criteriaMap[$code] ?? null;
            if (! $id) {
                continue;
            }

            $rows[] = [
                'review_id'          => $reviewId,
                'review_criteria_id' => $id,
                'rating'             => $value,
                'created_at'         => $now,
                'updated_at'         => $now,
            ];
        }

        if (! empty($rows)) {
            DB::table('review_rating')->insert($rows);
        }
    }

    protected function attachTags(int $reviewId, array $tagCodes): void
    {
        $tagIds = ReviewTag::active()
            ->whereIn('code', $tagCodes)
            ->pluck('id');

        if ($tagIds->isEmpty()) {
            return;
        }

        $now = now();
        $rows = $tagIds->map(fn ($id) => [
            'review_id'     => $reviewId,
            'review_tag_id' => $id,
            'created_at'    => $now,
        ])->all();

        DB::table('review_tag_pivot')->insertOrIgnore($rows);
        ReviewTag::whereIn('id', $tagIds)->increment('usage_count');
    }

    protected function attachMedia(Review $review, array $files): void
    {
        $imageCount = 0;
        $videoCount = 0;
        $sortOrder  = 0;
        $rows       = [];
        $now        = now();

        foreach ($files as $file) {
            if (! ($file instanceof UploadedFile) || ! $file->isValid()) {
                continue;
            }

            $mime = (string) $file->getMimeType();
            $isVideo = Str::startsWith($mime, 'video/');
            $isImage = Str::startsWith($mime, 'image/');

            if (! $isVideo && ! $isImage) {
                continue;
            }
            if ($isImage && $imageCount >= 9) {
                continue;
            }
            if ($isVideo && $videoCount >= 1) {
                continue;
            }

            $relativePath = "review/{$review->id}";
            $stored = MyStorage::storeFile($file, $relativePath);
            if (! $stored) {
                continue;
            }

            $rows[] = [
                'review_id'  => $review->id,
                'type'       => $isVideo ? ReviewMedia::TYPE_VIDEO : ReviewMedia::TYPE_IMAGE,
                'url'        => $stored,
                'thumbnail'  => null,
                'mime'       => substr($mime, 0, 64),
                'file_size'  => $file->getSize(),
                'width'      => null,
                'height'     => null,
                'duration'   => null,
                'sort_order' => $sortOrder++,
                'is_active'  => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if ($isImage) {
                $imageCount++;
            } else {
                $videoCount++;
            }
        }

        if (! empty($rows)) {
            DB::table('review_media')->insert($rows);
            $review->update(['media_count' => $imageCount + $videoCount]);
        }
    }

    public function vote(int $reviewId, int $userId, int $voteType): array
    {
        $voteType = max(-1, min(1, $voteType));

        return DB::transaction(function () use ($reviewId, $userId, $voteType) {
            $review = Review::lockForUpdate()->findOrFail($reviewId);

            $existing = ReviewHelpful::where('review_id', $reviewId)
                ->where('user_id', $userId)
                ->lockForUpdate()
                ->first();

            $oldVote = $existing?->vote_type ?? 0;

            if ($existing) {
                $existing->vote_type = $voteType;
                $existing->ip = getIpVisitor();
                $existing->save();
            } else {
                ReviewHelpful::create([
                    'review_id' => $reviewId,
                    'user_id'   => $userId,
                    'vote_type' => $voteType,
                    'ip'        => getIpVisitor(),
                ]);
            }

            $deltaHelpful   = ($voteType === 1 ? 1 : 0) - ($oldVote === 1 ? 1 : 0);
            $deltaUnhelpful = ($voteType === -1 ? 1 : 0) - ($oldVote === -1 ? 1 : 0);

            if ($deltaHelpful !== 0) {
                $review->helpful_count = max(0, $review->helpful_count + $deltaHelpful);
            }
            if ($deltaUnhelpful !== 0) {
                $review->unhelpful_count = max(0, $review->unhelpful_count + $deltaUnhelpful);
            }
            if ($deltaHelpful !== 0 || $deltaUnhelpful !== 0) {
                $review->save();
            }

            return [
                'helpful_count'   => (int) $review->helpful_count,
                'unhelpful_count' => (int) $review->unhelpful_count,
                'my_vote'         => $voteType,
            ];
        });
    }

    public function report(int $reviewId, int $userId, string $reasonCode, ?string $description = null): ReviewReport
    {
        return ReviewReport::updateOrCreate(
            ['review_id' => $reviewId, 'reported_by' => $userId],
            [
                'reason_code' => $reasonCode,
                'description' => $description,
                'status'      => getCoreConfig('review.status.pending'),
            ],
        );
    }
}
