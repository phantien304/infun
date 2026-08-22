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

class CustomerRepository extends QueryableRepository implements CustomerRepositoryInterface
{
    private const TYPE_CUSTOMER = UserType::Member->value;

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
        $deleted = (int) $request->input('deleted_at', -1);
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

    /**
     * Duyệt khách theo lô để dựng danh sách người nhận của chiến dịch mail.
     *
     * `chunkById` chứ KHÔNG `get()`: "gửi cho tất cả khách" trên shop thật là
     * vài chục nghìn dòng — nạp hết vào RAM rồi mới xử lý là cách bản mt219
     * chết khi data lớn. Callback nhận từng lô [['email'=>..,'user_id'=>..]].
     *
     * Chỉ lấy khách CÒN SỐNG (không lấy soft-deleted) và có email — gửi
     * marketing cho tài khoản đã xoá là chuyện không ai muốn giải thích.
     *
     * @param  callable(array<int, array{email: string, user_id: int}>): void  $callback
     */
    public function chunkRecipients(
        bool $newsletterOnly,
        ?int $userGroupId,
        ?array $userIds,
        callable $callback,
        int $chunkSize = 500,
    ): void {
        $query = $this->resetModel()->newQuery()
            ->where('type', self::TYPE_CUSTOMER)
            ->whereNotNull('email')
            ->where('email', '<>', '')
            ->select(['id', 'email']);

        if ($newsletterOnly) {
            $query->where('newsletter', 1);
        }
        if ($userGroupId !== null) {
            $query->where('user_group_id', $userGroupId);
        }
        if ($userIds !== null) {
            $query->whereIn('id', $userIds ?: [0]);
        }

        $query->chunkById($chunkSize, function ($users) use ($callback) {
            $callback(
                $users->map(fn ($u) => ['email' => (string) $u->email, 'user_id' => (int) $u->id])->all(),
            );
        });
    }

    /**
     * Tắt cờ nhận tin theo email (link huỷ đăng ký trong mail marketing).
     * Trả về số dòng đổi — 0 nghĩa là email không thuộc khách nào, vẫn coi là
     * thành công với người bấm (không tiết lộ email đó có tài khoản hay không).
     */
    public function optOutNewsletterByEmail(string $email): int
    {
        return $this->resetModel()->newQuery()
            ->where('type', self::TYPE_CUSTOMER)
            ->where('email', $email)
            ->update(['newsletter' => 0]);
    }
}
