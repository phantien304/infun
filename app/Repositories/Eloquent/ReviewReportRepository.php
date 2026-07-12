<?php

namespace App\Repositories\Eloquent;

use App\Models\Entities\ReviewReport;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Interfaces\ReviewReportRepositoryInterface;

class ReviewReportRepository extends QueryableRepository implements ReviewReportRepositoryInterface
{
    public function model(): string
    {
        return ReviewReport::class;
    }

    public function upsertReport(int $reviewId, int $userId, string $reasonCode, ?string $description, int $status): ReviewReport
    {
        return $this->resetModel()->updateOrCreate(
            ['review_id' => $reviewId, 'reported_by' => $userId],
            [
                'reason_code' => $reasonCode,
                'description' => $description,
                'status'      => $status,
            ],
        );
    }
}
