<?php

namespace App\Repositories\Interfaces;

use App\Models\Entities\ReviewReport;
use App\Repositories\Base\BaseRepositoryInterface;

interface ReviewReportRepositoryInterface extends BaseRepositoryInterface
{
    public function upsertReport(int $reviewId, int $userId, string $reasonCode, ?string $description, int $status): ReviewReport;
}
