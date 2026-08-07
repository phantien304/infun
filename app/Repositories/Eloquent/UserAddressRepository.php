<?php

namespace App\Repositories\Eloquent;

use App\Models\Entities\UserAddress;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Interfaces\UserAddressRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

/**
 * Repository address mới — replace stub legacy
 * `App\Repositories\Client\InfunStudio\UserAddressRepository`.
 *
 * KHÔNG cache: address là user-scoped + thay đổi thường xuyên (thêm/sửa/
 * mặc định lại). Per-user cache invalidation phức tạp hơn lợi ích read.
 *
 * Relation `description()` ở Zone/District/Ward đã tự `->forLocale()` nên
 * eager-load thẳng — KHÔNG cần closure scope `language_code` thủ công như
 * legacy. Repo này tin tưởng convention `description()` đã chuẩn.
 */
class UserAddressRepository extends QueryableRepository implements UserAddressRepositoryInterface
{
    public function model(): string
    {
        return UserAddress::class;
    }

    public function listForUser(int $userId): Collection
    {
        return $this->resetModel()
            ->where('user_id', $userId)
            ->with([
                'country',
                'zone.description',
                'district.description',
                'ward.description',
            ])
            ->orderBy('is_default', 'DESC')
            ->orderBy('id', 'DESC')
            ->get();
    }

    public function findForUser(int $userId, int $addressId): ?UserAddress
    {
        return $this->resetModel()
            ->where('user_id', $userId)
            ->where('id', $addressId)
            ->with([
                'zone.description',
                'district.description',
                'ward.description',
            ])
            ->first();
    }

    public function upsertForUser(int $userId, array $data): UserAddress
    {
        // 'id' (nếu có) CHỈ dùng để tìm record cũ bên dưới, KHÔNG phải field
        // ghi — bóc ra trước khi fill() (thêm 2026-08-05, phát hiện khi thêm
        // $fillable tường minh cho UserAddress: trước đó $fillable rỗng nên
        // HasSchemaCache tự suy TOÀN BỘ cột kể cả 'id', fill() với key 'id'
        // lọt qua êm; giờ 'id' không còn trong $fillable curated theo cột
        // thật sự cần ghi → AddressService::save() gọi vào đây sẽ ăn
        // MassAssignmentException nếu không bóc ra, vì $data vẫn còn key
        // 'id' (xem AddressService::save(), luôn truyền 'id' => null|int)).
        $id = (int) ($data['id'] ?? 0);
        unset($data['id']);

        $data['user_id'] = $userId;

        // Reset default cũ nếu request set default mới — đảm bảo invariant
        // "1 user có max 1 default".
        if (! empty($data['is_default'])) {
            $this->resetModel()
                ->where('user_id', $userId)
                ->update(['is_default' => 0]);
        }

        $address = $this->resetModel()
            ->where('user_id', $userId)
            ->where('id', $id)
            ->firstOrNew();
        $address->fill($data)->save();

        return $address;
    }

    public function deleteForUser(int $userId, int $addressId): bool
    {
        return (bool) $this->resetModel()
            ->where('user_id', $userId)
            ->where('id', $addressId)
            ->delete();
    }
}
