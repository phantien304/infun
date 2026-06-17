<?php

namespace App\Data\Concerns;

trait HasThumbnail
{
    public function thumbnail(int $width, int $height, string $module = 'web'): string
    {
        return \thumbnail((string) ($this->image ?? ''), $width, $height, $module);
    }
}
