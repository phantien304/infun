<?php

namespace App\Enums;

enum OptionRole: int
{
    case CustomField = 0;
    case Variant = 1;

    public static function fromInput(mixed $value): self
    {
        if ($value instanceof self) {
            return $value;
        }

        return is_numeric($value)
            ? (self::tryFrom((int) $value) ?? self::CustomField)
            : self::CustomField;
    }

    public function isVariant(): bool
    {
        return $this === self::Variant;
    }

    public function isCustomField(): bool
    {
        return $this === self::CustomField;
    }

    public function label(): string
    {
        return match ($this) {
            self::CustomField => 'Custom field',
            self::Variant => 'Variant',
        };
    }
}
