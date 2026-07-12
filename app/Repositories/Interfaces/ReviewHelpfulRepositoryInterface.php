<?php

namespace App\Repositories\Interfaces;

use App\Repositories\Base\BaseRepositoryInterface;

interface ReviewHelpfulRepositoryInterface extends BaseRepositoryInterface
{
    public function upsertVote(int $reviewId, int $userId, int $voteType, string $ip): int;
}
