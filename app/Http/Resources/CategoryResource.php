<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Chuẩn hoá output Category cho API REST.
 *  - index: có sẵn 'title' (join theo ngôn ngữ), không kèm descriptions.
 *  - show/store/update: kèm 'category_descriptions' (mọi ngôn ngữ) khi đã load.
 */
class CategoryResource extends JsonResource
{
    public function toArray($request): array
    {
        // index: title lấy từ cột join; show: lấy từ description đầu tiên đã load.
        $title = $this->title;
        if (! $title && $this->relationLoaded('descriptions')) {
            $title = optional($this->descriptions->first())->title;
        }

        return [
            'id'                    => (int) $this->id,
            'parent_id'             => (int) $this->parent_id,
            'sort_order'            => (int) $this->sort_order,
            'icon'                  => $this->icon,
            'image'                 => $this->image,
            'image_icon'            => $this->image_icon,
            'deleted_at'            => $this->deleted_at,
            'title'                 => $title,
            'category_descriptions' => $this->whenLoaded('descriptions'),
        ];
    }
}
