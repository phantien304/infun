<?php

namespace App\Enums;

enum OptionType: string
{
    case Select = 'select';
    case Radio = 'radio';
    case Checkbox = 'checkbox';
    case Image = 'image';
    case Text = 'text';
    case Textarea = 'textarea';
    case Email = 'email';
    case Phone = 'phone';
    case File = 'file';
    case Date = 'date';
    case Datetime = 'datetime';
    case Time = 'time';

    public function isVariantWidget(): bool
    {
        return in_array($this, [self::Select, self::Radio, self::Checkbox, self::Image], true);
    }

    public function expectsTextInput(): bool
    {
        return in_array($this, [self::Text, self::Textarea, self::Email, self::Phone], true);
    }

    public function expectsDateOrFileChoice(): bool
    {
        return in_array($this, [self::File, self::Datetime, self::Date, self::Time], true);
    }
}
