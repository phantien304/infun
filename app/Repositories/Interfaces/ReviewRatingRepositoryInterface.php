<?php

namespace App\Repositories\Interfaces;

use App\Repositories\Base\BaseRepositoryInterface;

interface ReviewRatingRepositoryInterface extends BaseRepositoryInterface
{
    public function insert(array $rows): void;

    /**
     * Sửa rating theo từng tiêu chí cho 1 review đã có (CMS) — upsert theo
     * khoá (review_id, review_criteria_id) đã có sẵn (KHÔNG tạo tiêu chí
     * mới cho review chưa từng có).
     */
    public function upsertForReview(int $reviewId, array $ratings): void;
}
