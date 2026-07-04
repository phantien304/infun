<?php

namespace App\Repositories\Interfaces;

use App\Repositories\Base\BaseRepositoryInterface;
use Illuminate\Support\Collection;

interface LanguageRepositoryInterface extends BaseRepositoryInterface
{
    public function listAllCached(): Collection;
}
