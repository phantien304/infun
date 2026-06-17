<?php

namespace App\Repositories\Interfaces;

use App\Models\Entities\Manufacturer;
use App\Repositories\Base\BaseRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

interface ManufacturerRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * Cached snapshot used by Controller::render() (system-wide load).
     * Driven by rememberSystem (always cached, even when config_debug=1).
     */
    public function listAllCached(): Collection;

    /**
     * Fetch a single manufacturer for the public detail/listing page.
     * Returns null when the id is invalid or the row was soft-deleted.
     */
    public function getManufacturerDetail(int $id): ?Manufacturer;
}
