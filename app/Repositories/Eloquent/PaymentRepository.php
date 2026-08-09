<?php

namespace App\Repositories\Eloquent;

use App\Models\Entities\Payment;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Concerns\CacheableRepository;
use App\Repositories\Interfaces\PaymentRepositoryInterface;
use Illuminate\Support\Collection;

class PaymentRepository extends QueryableRepository implements PaymentRepositoryInterface
{
    use CacheableRepository;

    public function model(): string
    {
        return Payment::class;
    }

    public function listAllCached(): Collection
    {
        return $this->rememberCache(
            setting('cache.payments'),
            fn () => $this->resetModel()
                ->with('description')
                ->orderBy('sort_order', 'DESC')
                ->orderBy('id', 'DESC')
                ->get()
        );
    }

    public function findByCode(string $code): ?Payment
    {
        return $this->rememberCache(
            setting('cache.payments') . $code,
            fn () => $this->resetModel()
                ->with('description')
                ->where('code', $code)
                ->first()
        );
    }

    public function flushCache(): void
    {
        $this->forgetCache(setting('cache.payments'));
    }
}
