<?php

namespace App\Repositories\Eloquent;

use App\Models\Entities\UserAddress;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Interfaces\UserAddressRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class UserAddressRepository extends QueryableRepository implements UserAddressRepositoryInterface
{
    public function model(): string
    {
        return UserAddress::class;
    }

    public function listForUser(int $userId): Collection
    {
        return $this->resetModel()
            ->where('user_id', $userId)
            ->with([
                'country',
                'zone.description',
                'district.description',
                'ward.description',
            ])
            ->orderBy('is_default', 'DESC')
            ->orderBy('id', 'DESC')
            ->get();
    }

    public function findForUser(int $userId, int $addressId): ?UserAddress
    {
        return $this->resetModel()
            ->where('user_id', $userId)
            ->where('id', $addressId)
            ->with([
                'zone.description',
                'district.description',
                'ward.description',
            ])
            ->first();
    }

    public function upsertForUser(int $userId, array $data): UserAddress
    {
        $id = (int) ($data['id'] ?? 0);
        unset($data['id']);

        $data['user_id'] = $userId;

        if (! empty($data['is_default'])) {
            $this->resetModel()
                ->where('user_id', $userId)
                ->update(['is_default' => 0]);
        }

        $address = $this->resetModel()
            ->where('user_id', $userId)
            ->where('id', $id)
            ->firstOrNew();
        $address->fill($data)->save();

        return $address;
    }

    public function deleteForUser(int $userId, int $addressId): bool
    {
        return (bool) $this->resetModel()
            ->where('user_id', $userId)
            ->where('id', $addressId)
            ->delete();
    }

    public function setDefault(int $userId, int $addressId): bool
    {
        $this->resetModel()
            ->where('user_id', $userId)
            ->update(['is_default' => 0]);

        return (bool) $this->resetModel()
            ->where('user_id', $userId)
            ->where('id', $addressId)
            ->update(['is_default' => 1]);
    }
}
