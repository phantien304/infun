<?php

namespace App\Repositories\Eloquent;

use App\Models\Entities\Role;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Interfaces\RoleRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class RoleRepository extends QueryableRepository implements RoleRepositoryInterface
{
    private const GUARD = 'web';

    public function model(): string
    {
        return Role::class;
    }

    public function listForCms(Request $request): LengthAwarePaginator
    {
        $sort    = $request->input('sort') === 'name' ? 'name' : 'id';
        $order   = strtolower((string) $request->input('order', 'desc')) === 'asc' ? 'asc' : 'desc';
        $keyword = trim((string) $request->input('keyword', ''));
        $perPage = max(1, (int) $request->input('per_page', 50));

        // KHÔNG dùng Role::query() trần — Role::query() dựng model qua
        // `new Role()` (attributes rỗng), constructor của spatie Role tự suy
        // guard_name qua Guard::getDefaultName() khi thiếu. Sau khi qua
        // middleware auth:sanctum, Sanctum gọi Auth::shouldUse('sanctum') →
        // ghi đè config('auth.defaults.guard') thành 'sanctum' cho HẾT
        // request — Guard::getDefaultName() đọc đúng giá trị đã bị ghi đè đó
        // (không map được guard 'sanctum' -> provider nào) → guard_name của
        // model rỗng suy ra 'sanctum'. withCount('users') gọi
        // Role::users() -> getModelForGuard('sanctum') trả null ->
        // morphedByMany(null, ...) -> "Class name must be a valid object or
        // a string" (chỉ lộ khi đi qua route thật có auth:sanctum, KHÔNG lộ
        // khi test qua tinker/CLI hay gọi controller trực tiếp — CLI không
        // có middleware này nên config('auth.defaults.guard') vẫn là 'web').
        // Truyền thẳng guard_name để bỏ qua suy đoán của constructor.
        $query = (new Role(['guard_name' => self::GUARD]))
            ->newQuery()
            ->where('guard_name', self::GUARD)
            ->withCount(['permissions', 'users']);

        if ($keyword !== '') {
            $query->where('name', 'like', '%' . $keyword . '%');
        }

        return $query->orderBy($sort, $order)->paginate($perPage);
    }

    public function getForCms(int $id): ?Role
    {
        return $this->resetModel()->with('permissions')->find($id);
    }
}
