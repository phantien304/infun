<?php

namespace App\Data\Output;

use App\Models\Entities\User;
use Spatie\LaravelData\Data;

/**
 * DTO user cho frontend account + blog author.
 *
 * Property camelCase theo convention CLAUDE.md. Field `phone` đọc từ relation
 * `userPhone` (1:1) — eager-load ở repo trước khi `fromModel`. Field
 * `typeRegister` cho blade password phân biệt social-login (không cần
 * old_password) vs user thường.
 *
 * Field `fullName` / `address` / `avatar` đặt nullable để backward-compatible
 * với BlogDTO::user (blog author không có phone/sex/newsletter).
 */
class UserDTO extends Data
{
    public function __construct(
        public int $id,
        public string $fullName,
        public string $email,
        public ?string $phone,
        public ?int $sex,
        public ?int $newsletter,
        public ?string $typeRegister,
        public ?string $address,
        public ?string $avatar,
    ) {
    }

    public static function fromModel(User $user): self
    {
        $phone = null;
        if ($user->relationLoaded('userPhone') && $user->userPhone) {
            $phone = (string) ($user->userPhone->phone ?? '');
        }

        return new self(
            id: (int) $user->id,
            fullName: (string) ($user->full_name ?? ''),
            email: (string) ($user->email ?? ''),
            phone: $phone,
            sex: isset($user->sex) ? (int) $user->sex : null,
            newsletter: isset($user->newsletter) ? (int) $user->newsletter : null,
            typeRegister: $user->type_register ?? null,
            address: $user->address ?? null,
            avatar: $user->avatar ?? null,
        );
    }
}
