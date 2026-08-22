<?php

namespace App\Data\Cms;

use App\Models\Entities\OrdersStatus;
use Spatie\LaravelData\Data;

class OrderStatusDescriptionData extends Data
{
    public function __construct(
        public string $language_code,
        public ?string $name,
    ) {
    }

    public static function fromModel(OrdersStatus $row): self
    {
        return new self(
            language_code: (string) $row->language_code,
            name: $row->name,
        );
    }
}
