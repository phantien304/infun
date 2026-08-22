<?php

namespace App\Data\Cms;

use App\Models\Entities\VoucherTheme;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Data;

/**
 * DTO voucher theme — mẫu thiệp gắn vào voucher (ảnh + tên đa ngữ).
 * Bảng `voucher_theme` không đổi so với mt219; chỉ đổi cách trả về
 * (Data DTO thay vì presenter PVoucherTheme).
 */
class VoucherThemeData extends Data
{
    public function __construct(
        public int $id,
        public string $image,
        public ?string $name,
        public ?string $deleted_at,
        public Collection $voucher_theme_descriptions,
    ) {
    }

    public static function fromModel(VoucherTheme $theme): self
    {
        return new self(
            id: (int) $theme->id,
            image: (string) $theme->image,
            name: $theme->name
                ?? ($theme->relationLoaded('voucherThemeDescriptions')
                    ? $theme->voucherThemeDescriptions->first()?->name
                    : null),
            deleted_at: $theme->deleted_at?->toDateTimeString(),
            voucher_theme_descriptions: $theme->relationLoaded('voucherThemeDescriptions')
                ? VoucherThemeDescriptionData::collect($theme->voucherThemeDescriptions, Collection::class)
                : collect(),
        );
    }
}
