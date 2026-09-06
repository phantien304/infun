<?php

namespace App\Repositories\Interfaces;

use App\Models\Entities\Payment;
use App\Repositories\Base\BaseRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface PaymentRepositoryInterface extends BaseRepositoryInterface
{
    public function listAllCached(): Collection;

    public function findByCode(string $code): ?Payment;

    // ===================== CMS (admin) =====================

    public function listForCms(Request $request): LengthAwarePaginator;

    public function getForCms(int $id): ?Payment;

    public function saveFromCms(?Payment $payment, array $data): Payment;

    public function deleteByIds(array $ids): int;

    public function restoreByIds(array $ids): int;

    public function restoreById(int $id): ?Payment;
}
