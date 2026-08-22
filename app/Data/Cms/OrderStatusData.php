<?php

namespace App\Data\Cms;

use App\Models\Entities\OrdersStatus;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Data;

class OrderStatusData extends Data
{
    public function __construct(
        public int $id,
        public ?string $name,
        public ?string $deleted_at,
        public Collection $order_status_descriptions,
    ) {
    }

    /** Dùng cho list (listForCms trả 1 dòng/status theo ngôn ngữ admin mặc định). */
    public static function fromModel(OrdersStatus $row): self
    {
        return new self(
            id: (int) $row->id,
            name: $row->name,
            deleted_at: $row->deleted_at?->toDateTimeString(),
            order_status_descriptions: collect(),
        );
    }

    /** Dùng cho detail/edit — $rows là TOÀN BỘ dòng ngôn ngữ của 1 status id. */
    public static function fromRows(int $id, Collection $rows): self
    {
        $active = $rows->first(fn (OrdersStatus $r) => ! $r->trashed()) ?? $rows->first();

        return new self(
            id: $id,
            name: $active?->name,
            deleted_at: $active?->deleted_at?->toDateTimeString(),
            order_status_descriptions: OrderStatusDescriptionData::collect(
                $rows->filter(fn (OrdersStatus $r) => ! $r->trashed())->values(),
                Collection::class
            ),
        );
    }
}
