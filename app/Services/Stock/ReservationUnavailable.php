<?php

namespace App\Services\Stock;

use RuntimeException;

class ReservationUnavailable extends RuntimeException
{
    public function __construct(public readonly array $info)
    {
        parent::__construct('Reservation unavailable');
    }
}
