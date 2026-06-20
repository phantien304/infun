# Nhánh demo: phân quyền bằng spatie/laravel-permission

Nhánh `feature/spatie-permission` để bạn so sánh trực tiếp với cách middleware tự
viết (`CheckPermission`). Mục tiêu: cùng bộ mã quyền cũ (`list/detail/create/edit/del-{entity}`)
nhưng chạy qua spatie + Gate/Policy chuẩn Laravel.

> Sandbox không có PHP/Composer nên các lệnh dưới đây bạn chạy trên máy (Herd).
> spatie tạo bảng riêng; để KHÔNG đụng bảng `permissions`/`roles` đang có, ta đổi
> tên bảng spatie thành `sp_*` (xem bước 3).

---

## 1. Cài package

```bash
cd E:\xampp82\htdocs\infun
composer require spatie/laravel-permission
```

## 2. Publish config + migration

```bash
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
```

Tạo `config/permission.php` và 1 migration trong `database/migrations/`.

## 3. Đổi tên bảng spatie (tránh trùng `permissions` / `roles` cũ)

Trong `config/permission.php`, sửa `table_names`:

```php
'table_names' => [
    'roles'                 => 'sp_roles',
    'permissions'           => 'sp_permissions',
    'model_has_permissions' => 'sp_model_has_permissions',
    'model_has_roles'       => 'sp_model_has_roles',
    'role_has_permissions'  => 'sp_role_has_permissions',
],
```

(Migration đọc `table_names` lúc chạy nên sẽ tạo đúng các bảng `sp_*`.)

## 4. Migrate

```bash
php artisan migrate
```

## 5. Thêm trait vào User

`app/Models/Entities/User.php`:

```php
use Spatie\Permission\Traits\HasRoles;

class User extends CmsUser
{
    use HasApiTokens;
    use SoftDeletes;
    use HasRoles;           // <-- thêm
    // ...
}
```

> User phải là `Authorizable` (Laravel `Authenticatable` đã có sẵn) để `can()` chạy.
> Nếu guard không khớp, thêm `protected $guard_name = 'web';` vào User.

## 6. Seed quyền + role demo

File `database/seeders/SpatiePermissionSeeder.php` (đã có trong nhánh) import mã quyền
từ bảng cũ `permissions` sang `sp_*`, tạo role `admin` (đủ quyền) và gán cho user đầu tiên.

```bash
php artisan db:seed --class=Database\\Seeders\\SpatiePermissionSeeder
```

## 7. Gắn kiểm tra quyền vào CategoryController (REST)

spatie đăng ký mỗi permission thành ability của Gate → dùng `Gate::authorize()` ngay
trong từng method (không cần sửa base Controller). Thêm `use Illuminate\Support\Facades\Gate;`
rồi đặt 1 dòng đầu mỗi method:

```php
public function index(Request $request)      { Gate::authorize('list-category');   /* ... */ }
public function show(Category $category)      { Gate::authorize('detail-category'); /* ... */ }
public function store(CategoryRequest $r)     { Gate::authorize('create-category'); /* ... */ }
public function update(CategoryRequest $r, Category $category) { Gate::authorize('edit-category'); /* ... */ }
public function destroy(Category $category)   { Gate::authorize('del-category');    /* ... */ }
public function restore($id)                  { Gate::authorize('del-category');    /* ... */ }
public function bulk(Request $request)        { Gate::authorize('del-category');    /* ... */ }
```

Quyền sai → spatie/Gate tự trả **403** (đúng REST).

**Cách khác (route middleware)** — nếu muốn khai báo ở route thay vì controller:

```php
Route::get('category',        [CategoryController::class, 'index'])->middleware('permission:list-category');
Route::post('category',       [CategoryController::class, 'store'])->middleware('permission:create-category');
Route::get('category/{id}',   [CategoryController::class, 'show'])->middleware('permission:detail-category');
Route::put('category/{id}',   [CategoryController::class, 'update'])->middleware('permission:edit-category');
Route::delete('category/{id}',[CategoryController::class, 'destroy'])->middleware('permission:del-category');
```

(Lúc này KHÔNG dùng được `apiResource` 1 dòng vì mỗi method cần mã quyền khác nhau —
đây là điểm khác so với `cmsResource`/middleware tự suy.)

**Cách policy** — `app/Policies/CategoryPolicy.php` đã có sẵn (map sang `can('...-category')`),
dùng khi bạn cho base Controller `use AuthorizesRequests` rồi gọi `$this->authorize('viewAny', Category::class)`.

## 8. Trả quyền cho frontend (login)

`Api\Cms\AuthController::resolvePermissions()` đổi sang spatie:

```php
private function resolvePermissions($user): array
{
    return $user->getAllPermissions()->pluck('name')->all();
}
```

→ Frontend nhận đúng danh sách `list-category`, `edit-category`... để ẩn/hiện nút.

## 9. Test

- Gán/bỏ quyền: `$user->givePermissionTo('edit-category')` / `revokePermissionTo(...)`,
  hoặc qua role. Gọi `PUT /rcms/category/{id}` → có quyền 200, không quyền 403.
- `php artisan permission:show` để xem ma trận role × permission.

---

## So sánh nhanh với middleware tự viết

| | Middleware tự viết (`CheckPermission`) | spatie + Gate/Policy |
|---|---|---|
| Khai báo quyền ở route | Tự suy từ tên method+controller (0 dòng/route) | Mỗi action 1 dòng `Gate::authorize` / `permission:` |
| Dữ liệu quyền | Bảng `permissions` cũ | Bảng `sp_*` (import từ cũ) |
| Tích hợp Laravel | Riêng | `can()`, `@can`, policy, `Gate::authorize`, middleware `permission:`/`role:` |
| Tính năng thêm | Tự code | Wildcard (`category.*`), teams, cache quyền sẵn, `permission:show` |
| Phụ thuộc | Không | +1 package, +5 bảng |
| Công sức/entity | Gần như 0 | Phải gắn quyền cho từng action |

**Nhận xét:** spatie "chuẩn cộng đồng" + nhiều tiện ích (wildcard/cache/teams/blade), nhưng
mất tính "tự suy mã quyền theo route" của bạn → mỗi action phải gắn tay. Với ~40 entity, cách
tự viết ít lặp hơn; spatie đáng giá khi cần wildcard/teams/cache hoặc theo chuẩn để dev mới dễ vào.

## Gỡ bỏ (nếu không dùng)

```bash
php artisan migrate:rollback   # bỏ bảng sp_*
composer remove spatie/laravel-permission
git checkout product           # quay lại nhánh cũ
```
