<?php

namespace App\Data\Cms;

use App\Models\Entities\StockStatus;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Data;

class StockStatusData extends Data
{
    public function __construct(
        public int $id,
        public ?string $name,
        public ?string $deleted_at,
        public Collection $stock_status_descriptions,
    ) {
    }

    public static function fromModel(StockStatus $status): self
    {
        $name = $status->name
            ?? ($status->relationLoaded('descriptions')
                ? $status->descriptions->first()?->name
                : null);

        return new self(
            id: (int) $status->id,
            name: $name,
            deleted_at: $status->deleted_at?->toDateTimeString(),
            stock_status_descriptions: $status->relationLoaded('descriptions')
                ? StockStatusDescriptionData::collect($status->descriptions, Collection::class)
                : collect(),
        );
    }
}
