<?php

namespace App\Models\Traits;

use Illuminate\Database\Eloquent\Builder;

trait HasCompositeKey
{
    public function getKey($key = ''): mixed
    {
        if ($key === true) {
            return $this->getAttribute($this->getKeyName(true));
        }

        if ($key) {
            return $this->getAttribute($key);
        }

        $keys = $this->getKeyName();

        if (!is_array($keys)) {
            return parent::getKey();
        }

        return array_combine($keys, array_map(fn($k) => $this->getAttribute($k), $keys));
    }
    public function getKeyName($getFirst = false): mixed
    {
        $keys = parent::getKeyName();

        return $getFirst ? ((array)$keys)[0] : $keys;
    }
    public function getKeyWithName(string $key = ''): array
    {
        if ($key) {
            return [$key => $this->getAttribute($key)];
        }

        $keys = (array) $this->getKeyName();

        return array_combine($keys, array_map(fn($k) => $this->getAttribute($k), $keys));
    }
    public function isEmptyKey(): bool
    {
        $key = $this->getKey();

        return is_array($key)
            ? empty(array_filter($key, fn($v) => $v !== null && $v !== ''))
            : ($key === null || $key === '');
    }
    public function getKeyAsString(): string
    {
        return implode('k_k', (array) $this->getKey());
    }
    public function getKeyNameAsString(): string
    {
        return implode('k_k', (array) $this->getKeyName());
    }
    public function getKeyFromString(string $value): array
    {
        $values = explode('k_k', $value);
        $keys   = (array) $this->getKeyName();

        return array_combine($keys, array_pad($values, count($keys), null));
    }
    public function setOriginKeyFromString(string $value): static
    {
        foreach ($this->getKeyFromString($value) as $col => $val) {
            $this->original[$col] = $val;
        }

        return $this;
    }
    protected function setKeysForSaveQuery($query)
    {
        $keys = $this->getKeyName();

        if (!is_array($keys)) {
            return parent::setKeysForSaveQuery($query);
        }

        foreach ($keys as $key) {
            $query->where($key, '=', $this->getKeyForSaveQuery($key));
        }

        return $query;
    }
    protected function getKeyForSaveQuery($keyName = null): mixed
    {
        $keyName ??= $this->getKeyName();

        return $this->original[$keyName] ?? $this->getAttribute($keyName);
    }
}
