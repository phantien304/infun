<?php

namespace App\Data\Cms;

use App\Models\Entities\User;
use Spatie\LaravelData\Data;

/**
 * DTO cho màn Customer CMS — CHỈ member (type=2, App\Enums\UserType::Member).
 * Mirror UserData (Phase 2.3, admin type=1) nhưng thêm field riêng của
 * Customer: phone (qua relation userPhone, KHÔNG có ở Admin form),
 * address/sex/newsletter/user_group_id (cột trực tiếp trên `user`, xem
 * CustomerRepositoryInterface docblock). KHÔNG có `roles` (Customer không
 * gán role CMS). KHÔNG BAO GIỜ chứa `password`.
 */
class CustomerData extends Data
{
    public function __construct(
        public int $id,
        public string $email,
        public string $full_name,
        public ?string $avatar,
        public ?string $phone,
        public ?string $address,
        public ?int $sex,
        public int $newsletter,
        public ?int $user_group_id,
        public ?string $user_group_name,
        public int $status,
        public ?string $deleted_at,
    ) {
    }

    public static function fromModel(User $user): self
    {
        return new self(
            id: (int) $user->id,
            email: $user->email,
            full_name: $user->full_name,
            avatar: $user->avatar,
            phone: $user->relationLoaded('userPhone') ? $user->userPhone?->phone : null,
            address: $user->address,
            sex: $user->sex === null ? null : (int) $user->sex,
            newsletter: (int) $user->newsletter,
            user_group_id: $user->user_group_id === null ? null : (int) $user->user_group_id,
            user_group_name: $user->relationLoaded('userGroup') ? $user->userGroup?->name : null,
            status: (int) $user->status,
            deleted_at: $user->deleted_at?->toDateTimeString(),
        );
    }
}
