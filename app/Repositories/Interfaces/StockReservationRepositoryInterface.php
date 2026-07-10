<?php

namespace App\Repositories\Interfaces;

use App\Models\Entities\StockReservation;
use App\Repositories\Base\BaseRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

interface StockReservationRepositoryInterface extends BaseRepositoryInterface
{
    public function reservationsForVariant(string $holder, int $variantId): Collection;

    public function reservationsForHolder(string $holder): Collection;

    public function expiredReservations(int $limit): Collection;

    public function holderReservedMap(string $holder): array;

    public function createReservation(array $data): StockReservation;

    public function saveReservation(StockReservation $reservation): void;

    public function deleteReservation(StockReservation $reservation): void;
}
