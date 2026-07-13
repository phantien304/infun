<?php

namespace App\Repositories\Eloquent;

use App\Models\Entities\StockReservation;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Concerns\CacheableRepository;
use App\Repositories\Interfaces\StockReservationRepositoryInterface;
use App\Services\Stock\WarehouseService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

class StockReservationRepository extends QueryableRepository implements StockReservationRepositoryInterface
{
    use CacheableRepository;

    public function model(): string
    {
        return StockReservation::class;
    }

    public function reservationsForVariant(string $holder, int $variantId): Collection
    {
        return $this->resetModel()
        ->where('holder', $holder)
        ->where('product_variant_id', $variantId)
        ->get();
    }

    public function reservationsForHolder(string $holder): Collection
    {
        return $this->resetModel()
        ->where('holder', $holder)
        ->get();
    }

    public function expiredReservations(int $limit): Collection
    {
        return $this->resetModel()->whereNotNull('expires_at')
            ->where('expires_at', '<', Carbon::now())
            ->limit($limit)
            ->get();
    }

    public function holderReservedMap(string $holder): array
    {
        return $this->resetModel()->where('holder', $holder)
            ->whereIn('warehouse_id', app(WarehouseService::class)->sellableWarehouseIds())
            ->selectRaw('product_variant_id, SUM(quantity) AS total_quantity')
            ->groupBy('product_variant_id')
            ->pluck('total_quantity', 'product_variant_id')
            ->map(fn ($q) => (int) $q)
            ->all();
    }

    public function createReservation(array $data): StockReservation
    {
        return $this->resetModel()->create($data);
    }

    public function saveReservation(StockReservation $reservation): void
    {
        $reservation->save();
    }

    public function deleteReservation(StockReservation $reservation): void
    {
        $reservation->delete();
    }
}
