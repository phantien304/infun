<?php

namespace App\Data\Output;

use App\Models\Entities\UserAddress;
use Spatie\LaravelData\Data;

/**
 * Sổ địa chỉ giao hàng của user.
 *
 * `fullAddress` = ghép `address, ward, district, zone` (ngắn gọn cho list
 * blade). 3 field zoneName/districtName/wardName giữ riêng để form edit
 * pre-select option dropdown.
 *
 * Repo eager-load relation `zone.description` / `district.description` /
 * `ward.description` (locale-scoped) trước khi gọi `fromModel`. DTO check
 * `relationLoaded` để tránh trigger lazy load âm thầm khi caller skip
 * eager-load (vd findForUser).
 */
class UserAddressDTO extends Data
{
    public function __construct(
        public int $id,
        public string $fullName,
        public string $telephone,
        public string $address,
        public int $zoneId,
        public int $districtId,
        public int $wardId,
        public string $zoneName,
        public string $districtName,
        public string $wardName,
        public string $fullAddress,
        public bool $isDefault,
    ) {
    }

    public static function fromModel(UserAddress $address): self
    {
        $zoneName = self::descriptionName($address, 'zone');
        $districtName = self::descriptionName($address, 'district');
        $wardName = self::descriptionName($address, 'ward');
        $full = implode(', ', array_filter([
            $address->address ?? null,
            $wardName ?: null,
            $districtName ?: null,
            $zoneName ?: null,
        ]));

        return new self(
            id: (int) $address->id,
            fullName: (string) ($address->full_name ?? ''),
            telephone: (string) ($address->telephone ?? ''),
            address: (string) ($address->address ?? ''),
            zoneId: (int) ($address->zone_id ?? 0),
            districtId: (int) ($address->district_id ?? 0),
            wardId: (int) ($address->ward_id ?? 0),
            zoneName: $zoneName,
            districtName: $districtName,
            wardName: $wardName,
            fullAddress: $full,
            isDefault: (bool) ($address->is_default ?? false),
        );
    }

    private static function descriptionName(UserAddress $address, string $relation): string
    {
        if (! $address->relationLoaded($relation)) {
            return '';
        }
        $node = $address->{$relation};
        if (! $node || ! $node->relationLoaded('description')) {
            return '';
        }

        return (string) ($node->description->name ?? '');
    }
}
