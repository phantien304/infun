<?php

namespace App\Data\Output;

use App\Enums\GiftPickType;
use App\Enums\GiftTriggerType;
use App\Models\Entities\Gift;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
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
        return GiftTriggerType::fromInput($gift->trigger_type)?->label()
            ?? trans('messages.checkout.gift.trigger_special');
    }

    private static function resolveMinSubtotalLabel(Gift $gift): string
    {
        if ($gift->min_subtotal === null || (float) $gift->min_subtotal <= 0) {
            return trans('messages.checkout.gift.min_subtotal_any');
        }
        return sprintf(trans('messages.checkout.gift.min_subtotal_from'), money((float) $gift->min_subtotal));
    }

    private static function resolvePickTypeLabel(Gift $gift): string
    {
        $type = GiftPickType::fromInput($gift->pick_type);

        if ($type === GiftPickType::PickUpToN && (int) ($gift->pick_limit ?? 0) > 0) {
            return sprintf(trans('messages.checkout.gift.pick_up_to_limit'), (int) $gift->pick_limit);
        }

        return $type?->label() ?? trans('messages.checkout.gift.pick_fallback');
    }

    private static function resolveExpiresLabel(Gift $gift): string
    {
        if (! $gift->date_end) {
            return trans('messages.checkout.gift.expiry_none');
        }
        $end = Carbon::parse($gift->date_end);
        $now = Carbon::now();
        if ($end->lt($now)) {
            return trans('messages.checkout.gift.expiry_expired');
        }
        $diffDays = $now->diffInDays($end, false);
        if ($diffDays <= 7) {
            $diffHours = $now->diffInHours($end, false);
            if ($diffHours <= 24) {
                return sprintf(trans('messages.checkout.gift.expiry_hours'), max(1, (int) $diffHours));
            }
            return sprintf(trans('messages.checkout.gift.expiry_days'), (int) $diffDays);
        }
        return sprintf(trans('messages.checkout.gift.expiry_date'), $end->format('d/m/Y'));
    }
}
