<?php

namespace App\Enums;

enum ReviewPolicy: string
{
    case Public = 'public';
    case Login = 'login';
    case Purchase = 'purchase';

    public static function default(): self
    {
        return self::Public;
    }

    public static function fromInput(mixed $value): self
    {
        if ($value instanceof self) {
            return $value;
        }

        return is_string($value) ? (self::tryFrom($value) ?? self::default()) : self::default();
    }
}
