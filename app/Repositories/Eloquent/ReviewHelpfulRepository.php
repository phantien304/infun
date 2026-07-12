<?php

namespace App\Repositories\Eloquent;

use App\Models\Entities\ReviewHelpful;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Interfaces\ReviewHelpfulRepositoryInterface;

class ReviewHelpfulRepository extends QueryableRepository implements ReviewHelpfulRepositoryInterface
{
    public function model(): string
    {
        return ReviewHelpful::class;
    }

    public function upsertVote(int $reviewId, int $userId, int $voteType, string $ip): int
    {
        $existing = $this->resetModel()->where('review_id', $reviewId)
            ->where('user_id', $userId)
            ->lockForUpdate()
            ->first();

        $oldVote = $existing?->vote_type ?? 0;

        if ($existing) {
            $existing->vote_type = $voteType;
            $existing->ip = $ip;
            $existing->save();
        } else {
            ReviewHelpful::create([
                'review_id' => $reviewId,
                'user_id'   => $userId,
                'vote_type' => $voteType,
                'ip'        => $ip,
            ]);
        }

        return (int) $oldVote;
    }
}
