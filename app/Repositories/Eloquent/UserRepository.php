<?php

namespace App\Repositories\Eloquent;

use App\Enums\UserType;
use App\Models\Entities\User;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Interfaces\UserRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserRepository extends QueryableRepository implements UserRepositoryInterface
{
    public function model(): string
    {
        return User::class;
    }

    public function findById(int $id): ?User
    {
        return $this->resetModel()->where('id', $id)->first();
    }

    public function findByEmail(string $email): ?User
    {
        return $this->resetModel()->where('email', $email)->first();
    }

    public function findMemberByEmail(string $email): ?User
    {
        return $this->resetModel()
            ->where('email', $email)
            ->where('type', (int) getCoreConfig('user.type.member'))
            ->first();
    }

    public function findByConfirmCode(string $code, string $email): ?User
    {
        return $this->resetModel()
            ->where('confirm_code', $code)
            ->where('email', $email)
            ->first();
    }

    public function createUser(array $data): User
    {
        return $this->resetModel()->create($data);
    }

    public function markConfirmed(User $user): User
    {
        $user->fill(['confirmed' => 1])->save();

        return $user;
    }

    public function updatePasswordByEmail(string $email, string $plainPassword): ?User
    {
        $user = $this->resetModel()->where('email', $email)->lockForUpdate()->first();
        if (! $user) {
            return null;
        }
        $user->fill(['password' => Hash::make($plainPassword)])->save();

        return $user;
    }

    public function updatePasswordById(int $id, string $plainPassword): ?User
    {
        $user = $this->resetModel()->where('id', $id)->lockForUpdate()->first();
        if (! $user) {
            return null;
        }
        $user->fill(['password' => Hash::make($plainPassword)])->save();

        return $user;
    }

    public function getProfile(int $id): ?User
    {
        return $this->resetModel()
            ->where('id', $id)
            ->with('userPhone')
            ->first();
    }

    public function updateProfile(int $id, array $data): ?User
    {
        $user = $this->resetModel()->where('id', $id)->first();
        if (! $user) {
            return null;
        }
        $user->fill($data)->save();

        return $user;
    }

    public function searchForCms(string $keyword = '', ?int $type = null, int $limit = 10): \Illuminate\Database\Eloquent\Collection
    {
        $query = $this->resetModel()->newQuery()->with('userPhone');

        if ($type !== null) {
            $query->where('type', $type);
        }

        $keyword = trim($keyword);
        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $q->where('full_name', 'like', '%' . $keyword . '%')
                    ->orWhere('email', 'like', '%' . $keyword . '%');
            });
        }

        return $query->orderBy('full_name')->limit(max(1, $limit))->get();
    }

    private const TYPE_ADMIN = UserType::Admin->value;

    public function listForCms(Request $request): LengthAwarePaginator
    {
        $sort    = in_array($request->input('sort'), ['full_name', 'email'], true) ? $request->input('sort') : 'id';
        $order   = strtolower((string) $request->input('order', 'desc')) === 'asc' ? 'asc' : 'desc';
        $deleted = (int) $request->input('deleted_at', -1); // -1 tất cả, 1 hiển thị, 0 đã xoá
        $keyword = trim((string) $request->input('keyword', ''));
        $perPage = max(1, (int) $request->input('per_page', 50));

        $query = $this->resetModel()->newQuery()
            ->with('roles')
            ->where('type', self::TYPE_ADMIN);

        if ($deleted === 0) {
            $query->onlyTrashed();
        } elseif ($deleted === -1) {
            $query->withTrashed();
        }

        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $q->where('full_name', 'like', '%' . $keyword . '%')
                    ->orWhere('email', 'like', '%' . $keyword . '%')
                    ->orWhere('username', 'like', '%' . $keyword . '%');
            });
        }

        return $query->orderBy($sort, $order)->paginate($perPage);
    }

    public function getAdminForCms(int $id): ?User
    {
        return $this->resetModel()->withTrashed()->with('roles')
            ->where('type', self::TYPE_ADMIN)
            ->find($id);
    }

    public function createAdmin(array $data, array $roleIds): User
    {
        return DB::transaction(function () use ($data, $roleIds) {
            $user = $this->resetModel()->create([
                'username'  => $data['username'] ?? null,
                'email'     => $data['email'],
                'password'  => Hash::make($data['password']),
                'full_name' => $data['full_name'],
                'avatar'    => $data['avatar'] ?? null,
                'status'    => (int) ($data['status'] ?? 1),
                'type'      => self::TYPE_ADMIN,
            ]);

            $user->syncRoles($roleIds);

            return $user->load('roles');
        });
    }

    public function updateAdmin(User $user, array $data, array $roleIds): User
    {
        return DB::transaction(function () use ($user, $data, $roleIds) {
            $attributes = [
                'username'  => $data['username'] ?? null,
                'email'     => $data['email'],
                'full_name' => $data['full_name'],
                'avatar'    => $data['avatar'] ?? null,
                'status'    => (int) ($data['status'] ?? $user->status),
            ];
            if (! empty($data['password'])) {
                $attributes['password'] = Hash::make($data['password']);
            }

            $user->fill($attributes)->save();
            $user->syncRoles($roleIds);

            return $user->load('roles');
        });
    }

    public function deleteByIds(array $ids): int
    {
        return $this->resetModel()->whereIn('id', $ids)->where('type', self::TYPE_ADMIN)->delete();
    }

    public function restoreByIds(array $ids): int
    {
        return $this->resetModel()->withTrashed()->whereIn('id', $ids)->where('type', self::TYPE_ADMIN)->restore();
    }

    public function restoreById(int $id): ?User
    {
        $user = $this->resetModel()->withTrashed()->where('type', self::TYPE_ADMIN)->find($id);
        $user?->restore();

        return $user?->load('roles');
    }
}
