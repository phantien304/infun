<?php

namespace App\Exceptions;

use RuntimeException;

class CouponExhaustedException extends RuntimeException
{
    public const SCOPE_TOTAL = 'total';
    public const SCOPE_USER = 'user';

    public function __construct(
        public readonly int $couponId,
        public readonly string $scope,
        string $message = '',
    ) {
        parent::__construct($message !== '' ? $message : sprintf(
            'Coupon %d exhausted (%s limit reached)',
            $couponId,
            $scope,
        ));
    }
}
