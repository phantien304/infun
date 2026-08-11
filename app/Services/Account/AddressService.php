<?php

namespace App\Services\Account;

use App\Models\Entities\UserAddress;
use App\Repositories\Interfaces\UserAddressRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class AddressService
{
    public function __construct(
        protected UserAddressRepositoryInterface $addressRepo,
    ) {
    }

    public function listForUser(int $userId): Collection
    {
        return $this->addressRepo->listForUser($userId);
    }

    public function resolveDisplayList(): array
    {
        if (auth()->check()) {
            return $this->listForUserFormatted((int) auth()->id());
        }

        return json_decode(getCookie((string) getCoreConfig('cookie.user.address'), '[]'), true) ?: [];
    }

    public function listForUserFormatted(int $userId): array
    {
        return $this->listForUser($userId)->map(function (UserAddress $item) {
            $zoneName = (string) ($item->zone?->description?->name ?? '');
            $districtName = (string) ($item->district?->description?->name ?? '');
            $wardName = (string) ($item->ward?->description?->name ?? '');

            return [
                'id'            => $item->id,
                'full_name'     => $item->full_name,
                'telephone'     => $item->telephone,
                'zone_id'       => $item->zone_id,
                'zone_name'     => $zoneName,
                'district_id'   => $item->district_id,
                'district_name' => $districtName,
                'ward_id'       => $item->ward_id,
                'ward_name'     => $wardName,
                'address'       => $item->address,
                'full_address'  => implode(', ', array_filter([$item->address, $wardName, $districtName, $zoneName])),
                'is_default'    => (int) $item->is_default,
            ];
        })->all();
    }

    public function findForUser(int $userId, ?int $addressId): ?UserAddress
    {
        if (! filled($addressId)) {
            return null;
        }

        return $this->addressRepo->findForUser($userId, (int) $addressId);
    }

    public function save(int $userId, array $data): UserAddress
    {
        $isDefault = ! empty($data['is_default']) ? 1 : 0;
        $address = $this->addressRepo->upsertForUser($userId, [
            'id'           => $data['id']           ?? null,
            'full_name'    => $data['full_name']    ?? '',
            'telephone'    => $data['telephone']    ?? '',
            'country_id'   => getCoreConfig('zones.country_id_default'),
            'zone_id'      => $data['zone_id']      ?? null,
            'district_id'  => $data['district_id']  ?? null,
            'ward_id'      => $data['ward_id']      ?? null,
            'address'      => $data['address']      ?? '',
            'is_default'   => $isDefault,
        ]);

        $this->syncCookieFor($userId);

        return $address;
    }

    public function delete(int $userId, int $addressId): bool
    {
        $ok = $this->addressRepo->deleteForUser($userId, $addressId);
        if ($ok) {
            $this->syncCookieFor($userId);
        }

        return $ok;
    }

    public function applyQuickAddress(?int $userId, array $data): void
    {
        $fullAddress = implode(', ', array_filter([
            $data['address']       ?? null,
            $data['ward_name']     ?? null,
            $data['district_name'] ?? null,
            $data['zone_name']     ?? null,
        ]));

        if ($userId === null) {
            $payload = [array_merge($data, [
                'full_address' => $fullAddress,
                'is_default'   => 1,
            ])];
            $this->writeCookie($payload);

            return;
        }

        $cookieKey = (string) getCoreConfig('cookie.user.address');
        $current = json_decode((string) getCookie($cookieKey, '[]'));
        if (empty($current)) {
            $current = json_decode(json_encode($this->listForUser($userId)));
        }
        $pickedId = (int) ($data['id'] ?? 0);
        foreach ((array) $current as $item) {
            $item->is_default = ((int) ($item->id ?? 0) === $pickedId) ? 1 : 0;
        }
        $this->writeCookie($current);
    }

    public function clearVisitorCookie(): void
    {
        forgetCookie((string) getCoreConfig('cookie.user.address'));
    }

    protected function syncCookieFor(int $userId): void
    {
        $this->writeCookie($this->listForUserFormatted($userId));
    }

    protected function writeCookie(mixed $data): void
    {
        putCookie(
            (string) getCoreConfig('cookie.user.address'),
            json_encode($data),
            (int) getCoreConfig('cookie.time'),
        );
    }
}
