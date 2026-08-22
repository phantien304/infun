<?php

namespace App\Data\Cms;

use App\Models\Entities\ReviewReport;
use Spatie\LaravelData\Data;

class ReviewReportItemData extends Data
{
    public function __construct(
        public int $id,
        public int $reported_by,
        public string $reason_code,
        public ?string $description,
        public int $status,
        public ?int $resolved_by,
        public ?string $resolved_at,
        public ?string $resolution_note,
        public ?string $created_at,
    ) {
    }

    public static function fromModel(ReviewReport $m): self
    {
        return new self(
            id: (int) $m->id,
            reported_by: (int) $m->reported_by,
            reason_code: (string) $m->reason_code,
            description: $m->description,
            status: (int) $m->status,
            resolved_by: $m->resolved_by !== null ? (int) $m->resolved_by : null,
            resolved_at: $m->resolved_at?->toDateTimeString(),
            resolution_note: $m->resolution_note,
            created_at: $m->created_at?->toDateTimeString(),
        );
    }
}
