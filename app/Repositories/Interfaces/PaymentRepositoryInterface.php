<?php

namespace App\Repositories\Interfaces;

use App\Models\Entities\Payment;
use App\Repositories\Base\BaseRepositoryInterface;
use Illuminate\Support\Collection;

interface PaymentRepositoryInterface extends BaseRepositoryInterface
{
    public function listAllCached(): Collection;

    public function findByCode(string $code): ?Payment;
}
