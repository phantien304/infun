<?php

namespace App\Repositories\Eloquent;

use App\Models\Entities\Contact;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Interfaces\ContactRepositoryInterface;

/**
 * Write-only repo cho form liên hệ. Validate input nằm ở
 * `App\Http\Requests\Web\ContactSendRequest` (FormRequest) — KHÔNG dùng
 * validator legacy. Mass assignment dựa trên fillable suy từ schema
 * (trait HasSchemaCache ở Base).
 */
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
