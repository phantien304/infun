<?php

namespace App\Data\Output;

use App\Models\Entities\Gift;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;

class GiftDTO extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $description,
        public ?string $badge,
        public int $triggerType,
        public string $triggerLabel,
        public ?float $minSubtotal,
        public string $minSubtotalLabel,
        public int $pickType,
        public string $pickTypeLabel,
        public ?int $pickLimit,
        public ?string $dateStart,
        public ?string $dateEnd,
        public string $expiresAtLabel,
        public ?int $usesTotal,
        public int $usedCount,
        public ?int $usesRemaining,
        public Collection $items,
        public bool $availableToCart,
        public ?string $notAvailableReason,
        public array $pickedItemIds,
    ) {
    }

    public static function fromModel(
        Gift $gift,
        bool $availableToCart = true,
        ?string $notAvailableReason = null,
        array $pickedItemIds = [],
    ): self {
        return new self(
            id:                 (int) $gift->id,
            name:               (string) $gift->name,
            description:        $gift->description,
            badge:              $gift->badge,
            triggerType:        (int) $gift->trigger_type,
            triggerLabel:       self::resolveTriggerLabel($gift),
            minSubtotal:        $gift->min_subtotal !== null ? (float) $gift->min_subtotal : null,
            minSubtotalLabel:   self::resolveMinSubtotalLabel($gift),
            pickType:           (int) $gift->pick_type,
            pickTypeLabel:      self::resolvePickTypeLabel($gift),
            pickLimit:          $gift->pick_limit !== null ? (int) $gift->pick_limit : null,
            dateStart:          $gift->date_start?->format('Y-m-d H:i:s'),
            dateEnd:            $gift->date_end?->format('Y-m-d H:i:s'),
            expiresAtLabel:     self::resolveExpiresLabel($gift),
            usesTotal:          $gift->uses_total !== null ? (int) $gift->uses_total : null,
            usedCount:          (int) $gift->used_count,
            usesRemaining:      $gift->uses_total !== null
                ? max(0, (int) $gift->uses_total - (int) $gift->used_count)
                : null,
            items:              GiftItemDTO::collect($gift->items ?? collect()),
            availableToCart:    $availableToCart,
            notAvailableReason: $notAvailableReason,
            pickedItemIds:      array_values(array_map('intval', $pickedItemIds)),
        );
    }

    private static function resolveTriggerLabel(Gift $gift): string
    {
        $type = (int) $gift->trigger_type;
        if ($type === (int) getCoreConfig('gift.trigger_type.min_subtotal')) {
            return 'Đơn tối thiểu';
        }
        if ($type === (int) getCoreConfig('gift.trigger_type.buy_specific_product')) {
            return 'Mua sản phẩm chỉ định';
        }
        return 'Điều kiện đặc biệt';
    }

    private static function resolveMinSubtotalLabel(Gift $gift): string
    {
        if ($gift->min_subtotal === null || (float) $gift->min_subtotal <= 0) {
            return 'Không giới hạn';
        }
        return 'Đơn từ ' . money((float) $gift->min_subtotal);
    }

    private static function resolvePickTypeLabel(Gift $gift): string
    {
        $type = (int) $gift->pick_type;
        if ($type === (int) getCoreConfig('gift.pick_type.auto')) {
            return 'Tự động tặng kèm';
        }
        if ($type === (int) getCoreConfig('gift.pick_type.pick_1_of_n')) {
            return 'Chọn 1 quà';
        }
        if ($type === (int) getCoreConfig('gift.pick_type.pick_up_to_n')) {
            $limit = $gift->pick_limit !== null ? (int) $gift->pick_limit : 0;
            return $limit > 0 ? "Chọn tối đa {$limit} quà" : 'Chọn nhiều quà';
        }
        return 'Chọn quà';
    }

    private static function resolveExpiresLabel(Gift $gift): string
    {
        if (! $gift->date_end) {
            return 'Không giới hạn';
        }
        $end = Carbon::parse($gift->date_end);
        $now = Carbon::now();
        if ($end->lt($now)) {
            return 'Đã hết hạn';
        }
        $diffDays = $now->diffInDays($end, false);
        if ($diffDays <= 7) {
            $diffHours = $now->diffInHours($end, false);
            if ($diffHours <= 24) {
                return 'Còn ' . max(1, (int) $diffHours) . ' giờ';
            }
            return 'Còn ' . (int) $diffDays . ' ngày';
        }
        return 'HSD: ' . $end->format('d/m/Y');
    }
}
