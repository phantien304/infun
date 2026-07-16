<?php

namespace App\Exceptions;

use RuntimeException;

class InsufficientStockException extends RuntimeException
{
    public function __construct(
        public readonly int $productVariantId,
        public readonly int $requested,
        public readonly int $available,
        string $message = '',
    ) {
        parent::__construct(
            $message !== ''
                ? $message
                : sprintf(
                    'Insufficient stock for variant %d: requested %d, available %d',
                    $productVariantId,
                    $requested,
                    $available,
                ),
        );
    }
}
