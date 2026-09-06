<?php

namespace App\Data\Cms;

use App\Models\Entities\Payment;
use Spatie\LaravelData\Data;

class PaymentData extends Data
{
    public function __construct(
        public int $id,
        public string $code,
        public string $name,
        public ?string $image,
        public int $sort_order,
        public ?string $deleted_at,
    ) {
    }

    public static function fromModel(Payment $payment): self
    {
        return new self(
            id: (int) $payment->id,
            code: (string) $payment->code,
            name: (string) ($payment->description?->name ?? ''),
            image: $payment->image,
            sort_order: (int) $payment->sort_order,
            deleted_at: $payment->deleted_at?->toDateTimeString(),
        );
    }
}
