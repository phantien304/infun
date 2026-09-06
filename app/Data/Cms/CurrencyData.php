<?php

namespace App\Data\Cms;

use App\Models\Entities\Currency;
use Spatie\LaravelData\Data;

class CurrencyData extends Data
{
    public function __construct(
        public int $id,
        public string $code,
        public ?string $title,
        public ?string $unit,
        public float $value,
        public ?string $symbol_left,
        public ?string $symbol_right,
        public int $decimal_place,
        public int $sort_order,
        public ?string $deleted_at,
    ) {
    }

    public static function fromModel(Currency $currency): self
    {
        return new self(
            id: (int) $currency->id,
            code: (string) $currency->code,
            title: $currency->title,
            unit: $currency->unit,
            value: (float) $currency->value,
            symbol_left: $currency->symbol_left,
            symbol_right: $currency->symbol_right,
            decimal_place: (int) $currency->decimal_place,
            sort_order: (int) $currency->sort_order,
            deleted_at: $currency->deleted_at?->toDateTimeString(),
        );
    }
}
