<?php

namespace App\Enums;

enum StockPolicy: int
{
    case Deny = 0;
    case Backorder = 1;
    case Untracked = 2;

    public static function fromInput(mixed $value): self
    {
        if ($value instanceof self) {
            return $value;
        }
        if (is_numeric($value)) {
            return self::tryFrom((int) $value) ?? self::Deny;
        }

        return self::fromName(is_string($value) ? $value : null) ?? self::Deny;
    }

    public static function fromName(?string $name): ?self
    {
        return match (strtolower((string) $name)) {
            'deny'      => self::Deny,
            'backorder' => self::Backorder,
            'untracked' => self::Untracked,
            default     => null,
        };
    }

    public function bypassesStockCheck(): bool
    {
        return $this !== self::Deny;
    }

    public function tracksMovements(): bool
    {
        return $this !== self::Untracked;
    }
}
