<?php

namespace App\Data\Output;

use App\Models\Entities\Warehouse;
use Spatie\LaravelData\Data;

class WarehouseDTO extends Data
{
    public function __construct(
        public int $id,
        public string $name,
    ) {}

    public static function fromModel(Warehouse $warehouse): self
    {
        return new self(
            id: (int) $warehouse->id,
            name: (string) $warehouse->name,
        );
    }
}
