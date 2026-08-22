<?php

namespace App\Data\Cms;

use App\Models\Entities\Contact;
use Spatie\LaravelData\Data;

class ContactData extends Data
{
    public function __construct(
        public int $id,
        public ?string $name,
        public ?string $email,
        public ?string $company,
        public ?string $phone,
        public ?string $address,
        public ?string $service,
        public ?string $content,
        public int $is_read,
        public ?string $created_at,
        public ?string $deleted_at,
    ) {
    }

    public static function fromModel(Contact $contact): self
    {
        return new self(
            id: (int) $contact->id,
            name: $contact->name,
            email: $contact->email,
            company: $contact->company,
            phone: $contact->phone,
            address: $contact->address,
            service: $contact->service,
            content: $contact->content,
            is_read: (int) $contact->is_read,
            created_at: $contact->created_at?->toDateTimeString(),
            deleted_at: $contact->deleted_at?->toDateTimeString(),
        );
    }
}
