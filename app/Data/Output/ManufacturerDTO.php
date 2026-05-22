<?php

namespace App\Data\Output;

use App\Models\Entities\Manufacturer;
use Spatie\LaravelData\Data;

class ManufacturerDTO extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $image,
        public ?string $thumbnail,
        public ?string $sort_order,
        public ?string $slug,
        public ?string $url,
        public ?string $metaTitle,
        public ?string $metaDescription,
    ) {}
    public static function fromModel(Manufacturer $manufacturer): self
    {
        $name = (string) ($manufacturer->name ?? '');
        $slug  = resolveSlug('', $name);

        return new self(
            id: (int) $manufacturer->id,
            name: $name,
            image: $manufacturer->image,
            thumbnail: isset($manufacturer->image) ? thumbnail($manufacturer->image, 400, 400, 'web') : '',
            sort_order: $manufacturer->sort_order,
            slug: $slug,
            url: buildUrl($slug, getModuleConfig('url.manufacturer'), (int) $manufacturer->id),
            metaTitle: (string) ($manufacturer->meta_title ?? ''),
            metaDescription: (string) ($manufacturer->meta_description ?? ''),
        );
    }
}
