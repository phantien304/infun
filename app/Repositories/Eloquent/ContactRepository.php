<?php

namespace App\Repositories\Eloquent;

use App\Models\Entities\Contact;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Interfaces\ContactRepositoryInterface;

class ContactRepository extends QueryableRepository implements ContactRepositoryInterface
{
    public function model(): string
    {
        return Contact::class;
    }

    public function saveContact(array $data): ?Contact
    {
        try {
            return $this->resetModel()->create([
                'name'    => $data['name'] ?? null,
                'email'   => $data['email'] ?? null,
                'phone'   => $data['phone'] ?? null,
                'service' => $data['service'] ?? null,
                'content' => $data['content'] ?? null,
            ]);
        } catch (\Throwable $exception) {
            logError($exception->getMessage());

            return null;
        }
    }
}
