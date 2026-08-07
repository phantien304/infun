<?php

namespace App\Repositories\Eloquent;

use App\Enums\UserType;
use App\Models\Entities\User;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Interfaces\CustomerRepositoryInterface;
use App\Repositories\Interfaces\UserPhoneRepositoryInterface;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * CMS Customer (user `type=2`, UserType::Member) — mirror
 * UserRepository::listForCms/getAdminForCms/createAdmin/updateAdmin
 * (Phase 2.3) nhưng khác entity/quyền ('customer' thay 'user') + thêm field
 * phone (qua UserPhoneRepositoryInterface, cùng transaction) + address/sex/
 * newsletter/user_group_id (cột trực tiếp trên `user`, giống
 * AccountService::updateProfile).
 */
class CustomerRepository extends QueryableRepository implements CustomerRepositoryInterface
{
    private const TYPE_CUSTOMER = UserType::Member->value;

    /**
     * PHẢI gọi parent::__construct($app) — BaseRepository::__construct()
     * set $this->app (typed property Application) rồi tự gọi makeModel().
     * Bug thật (2026-08-05): bản đầu KHÔNG gọi parent::__construct(), khiến
     * $this->app không bao giờ khởi tạo → resetModel()/makeModel() ném
     * "Typed property BaseRepository::$app must not be accessed before
     * initialization" ngay khi gọi (list/create/update/delete đều dính).
     * Phát hiện qua Chrome click-through thật (nút Add), không phải chỉ đọc
     * code — UserRepository/UserGroupRepository không có constructor riêng
     * nên không dính bug này, chỉ CustomerRepository (constructor đầu tiên
     * trong nhóm Role/Permission có thêm dependency ngoài $app).
     */
    public function __construct(
        Application $app,
        private readonly UserPhoneRepositoryInterface $phoneRepo,
    ) {
        parent::__construct($app);
    }

    public function model(): string
    {
        return User::class;
    }

    public function listForCms(Request $request): LengthAwarePaginator
    {
        $sort    = in_array($request->input('sort'), ['full_name', 'email'], true) ? $request->input('sort') : 'id';
        $order   = strtolower((string) $request->input('order', 'desc')) === 'asc' ? 'asc' : 'desc';
        $deleted = (int) $request->input('deleted_at', -1); // -1 tất cả, 1 hiển thị, 0 đã xoá
        $keyword = trim((string) $request->input('keyword', ''));
        $perPage = max(1, (int) $request->input('per_page', 50));

        $query = $this->resetModel()->newQuery()
            ->with(['userPhone', 'userGroup'])
            ->where('type', self::TYPE_CUSTOMER);

        if ($deleted === 0) {
            $query->onlyTrashed();
        } elseif ($deleted === -1) {
            $query->withTrashed();
        }

        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $q->where('full_name', 'like', '%' . $keyword . '%')
                    ->orWhere('email', 'like', '%' . $keyword . '%');
            });
        }

        return $query->orderBy($sort, $order)->paginate($perPage);
    }

    public function getForCms(int $id): ?User
    {
        return $this->resetModel()->withTrashed()->with(['userPhone', 'userGroup'])
            ->where('type', self::TYPE_CUSTOMER)
            ->find($id);
    }

    public function createCustomer(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $user = $this->resetModel()->create([
                'email'         => $data['email'],
                'password'      => Hash::make($data['password']),
                'full_name'     => $data['full_name'],
                'avatar'        => $data['avatar'] ?? null,
                'address'       => $data['address'] ?? null,
                'sex'           => $data['sex'] ?? null,
                'newsletter'    => (int) ($data['newsletter'] ?? 0),
                'user_group_id' => $data['user_group_id'] ?? null,
                'status'        => (int) ($data['status'] ?? 1),
                'type'          => self::TYPE_CUSTOMER,
            ]);

            if (array_key_exists('phone', $data)) {
                $this->phoneRepo->upsertForUser($user->id, [
                    'phone'     => (string) $data['phone'],
                    'is_verify' => 0,
                ]);
            }

            return $user->load(['userPhone', 'userGroup']);
        });
    }

    public function updateCustomer(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data) {
            $attributes = [
                'email'         => $data['email'],
                'full_name'     => $data['full_name'],
                'avatar'        => $data['avatar'] ?? null,
                'address'       => $data['address'] ?? null,
                'sex'           => $data['sex'] ?? null,
                'newsletter'    => (int) ($data['newsletter'] ?? 0),
                'user_group_id' => $data['user_group_id'] ?? null,
                'status'        => (int) ($data['status'] ?? $user->status),
            ];
            if (! empty($data['password'])) {
                $attributes['password'] = Hash::make($data['password']);
            }

            $user->fill($attributes)->save();

            if (array_key_exists('phone', $data)) {
                $existing   = $this->phoneRepo->findForUser($user->id);
                $sameNumber = $existing !== null && (string) $existing->phone === (string) $data['phone'];
                $verified   = (bool) ($existing?->is_verify ?? false);

                $this->phoneRepo->upsertForUser($user->id, [
                    'phone'     => (string) $data['phone'],
                    // Đổi sang số khác = reset is_verify (khớp AccountService::updateProfile).
                    'is_verify' => ($sameNumber && $verified) ? 1 : 0,
                ]);
            }

            return $user->load(['userPhone', 'userGroup']);
        });
    }

    public function deleteByIds(array $ids): int
    {
        return $this->resetModel()->whereIn('id', $ids)->where('type', self::TYPE_CUSTOMER)->delete();
    }

    public function restoreByIds(array $ids): int
    {
        return $this->resetModel()->withTrashed()->whereIn('id', $ids)->where('type', self::TYPE_CUSTOMER)->restore();
    }

    public function restoreById(int $id): ?User
    {
        $user = $this->resetModel()->withTrashed()->where('type', self::TYPE_CUSTOMER)->find($id);
        $user?->restore();

        return $user?->load(['userPhone', 'userGroup']);
    }
}
