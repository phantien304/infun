<?php

namespace App\Repositories\Interfaces;

use App\Models\Entities\Contact;
use App\Repositories\Base\BaseRepositoryInterface;

interface ContactRepositoryInterface extends BaseRepositoryInterface
{
    public function saveContact(array $data): ?Contact;
}
