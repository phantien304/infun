<?php

namespace App\Models\Traits;

trait HasUrlAttributes
{
    protected array $urlAttributes = [];

    public function getAttribute($field)
    {
        $value = parent::getAttribute($field);

        if ($value && $this->hasUrlAttribute($field)) {
            return $this->getFileUrl($value);
        }

        return $value;
    }
    public function setAttribute($field, $value)
    {
        if (is_string($value) && $this->hasUrlAttribute($field)) {
            $value = str_replace($this->getFileUrl(''), '', $value);
        }

        return parent::setAttribute($field, $value);
    }
    protected function getArrayableItems(array $values): array
    {
        $urlAttributes = $this->getUrlAttributes();

        if (empty($urlAttributes)) {
            return parent::getArrayableItems($values);
        }

        foreach (array_intersect_key($values, array_flip($urlAttributes)) as $attr => $_) {
            if ($values[$attr]) {
                $values[$attr] = $this->getFileUrl($values[$attr]);
            }
        }

        return parent::getArrayableItems($values);
    }
    public function getUrlAttributes(): array
    {
        return $this->urlAttributes;
    }

    public function setUrlAttributes(array $urlAttributes): void
    {
        $this->urlAttributes = $urlAttributes;
    }

    public function hasUrlAttribute(string $field): bool
    {
        return in_array($field, $this->urlAttributes, true);
    }
}
