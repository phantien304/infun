<?php

namespace App\Data\Cms;

use App\Models\Entities\Warehouse;
use Spatie\LaravelData\Data;

class WarehouseData extends Data
{
    public function __construct(
        public int $id,
        public string $code,
        public ?string $name,
        public ?string $address,
        public ?int $zone_id,
        public ?int $district_id,
        public ?int $ward_id,
        public ?string $telephone,
        public int $priority,
        public bool $is_active,
        public bool $is_sellable,
        public ?string $deleted_at,
    ) {
    }

    public static function fromModel(Warehouse $warehouse): self
    {
        return new self(
            id: (int) $warehouse->id,
            code: $warehouse->code,
            name: $warehouse->name,
            address: $warehouse->address,
            zone_id: $warehouse->zone_id !== null ? (int) $warehouse->zone_id : null,
            district_id: $warehouse->district_id !== null ? (int) $warehouse->district_id : null,
            ward_id: $warehouse->ward_id !== null ? (int) $warehouse->ward_id : null,
            telephone: $warehouse->telephone,
            priority: (int) $warehouse->priority,
            is_active: (bool) $warehouse->is_active,
            is_sellable: (bool) $warehouse->is_sellable,
            deleted_at: $warehouse->deleted_at?->toDateTimeString(),
        );
    }
}
