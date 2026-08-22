<?php

namespace App\Repositories\Interfaces;

use App\Models\Entities\Contact;
use App\Repositories\Base\BaseRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

interface ContactRepositoryInterface extends BaseRepositoryInterface
{
    public function saveContact(array $data): ?Contact;

    // ----- CMS (admin) -----
    public function listForCms(Request $request): LengthAwarePaginator;

    public function getForCms(int $id): ?Contact;

    public function deleteByIds(array $ids): int;

    public function restoreByIds(array $ids): int;

    public function restoreById(int $id): ?Contact;
}
