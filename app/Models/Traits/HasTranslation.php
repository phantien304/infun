<?php

namespace App\Models\Traits;

use Illuminate\Support\Facades\App;

trait HasTranslation
{
    public function getAttributeName(string $key): string
    {
        return transa($this->getAlias(), $key);
    }
    public function getFormAttributesName(string $key = 'form_attributes'): array
    {
        $formAttrs = $this->getAttributesName($key);

        if (!is_array($formAttrs) || empty($formAttrs)) {
            return (array) $this->getAttributesName();
        }

        return $formAttrs;
    }
    public function getAttributesName(string $key = 'attributes'): mixed
    {
        return transm($this->getAlias() . '.' . $key);
    }
    public function getModelName(string $key = 'name'): mixed
    {
        return transm($this->getAlias() . '.' . $key);
    }
    public function tA(string $key): string
    {
        return $this->getAttributeName($key);
    }
}
