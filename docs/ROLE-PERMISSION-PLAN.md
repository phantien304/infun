# Kế hoạch convert Role/Permission/User/UserGroup (mt219 → infun/infuncms)

> Phác thảo 2026-08-03, dựa trên khảo sát trực tiếp code (không phải suy đoán —
> mọi finding dưới đây đã đọc source thật ở `mt219`, `infun`, `infuncms`).
> Đây là đợt convert 3 màn CMS còn thiếu (`role`, `user`, `user-group`) theo
> đúng pattern đã dùng cho `setting` (xem `SPATIE-PERMISSION.md`,
> `CMS-MODULE-BOUNDARY.md`, `app/Http/Controllers/Api/Cms/README.md`).
>
> **Đọc Phase 0 trước khi làm bất kỳ việc gì khác trong file này** — có 1 bug
> đang ảnh hưởng phân quyền của các entity ĐÃ LIVE (category/banner/product/
> blog/menu/order), không chỉ 3 entity sắp convert.

---

## 0. Hiện trạng (tóm tắt khảo sát)

### mt219 (Vue2) — nguồn convert

- 3 route CRUD: `role`, `user-group`, `user` (`Route::resource`, guard
  `check_permission` tự viết, KHÔNG phải spatie).
- **Không có màn "Permission" riêng.** `App\Http\Supports\Permissions` trait
  (`getPermissionsByController()`) tự suy danh sách quyền **lúc runtime**:
  quét `Route::getRoutes()`, lấy tên controller area `cms`, loại các tên
  trong `$_excepts = [auth, cms, setting, file, resource, report, mail]`,
  sinh `list/detail/create/edit/del-{entity}` cho phần còn lại. Khi Role
  lưu, permission được `Permission::firstOrCreate()` tại chỗ nếu chưa có —
  danh sách quyền tự phình theo controller, không ai cập nhật tay.
- **Role form**: 2 cột — info role + ma trận checkbox phẳng (hàng = entity,
  cột = 5 action, có "check-all" theo cột). Save xoá hết `permission_role`
  của role rồi insert lại từ đầu.
- **User form**: 1 form dùng chung Admin + Member (cột `type`: 1=Admin,
  2=Member). Admin chọn **đúng 1** `role_id` (lấy từ pivot `role_user`, dù
  bảng hỗ trợ nhiều role, UI chỉ cho chọn 1). Member có thêm `user_group_id`,
  phone, address, sex, newsletter...
- **UserGroup**: entity đơn giản, đa ngôn ngữ (`approval`, `sort_order`,
  description theo locale) — cùng pattern `category`/`blog` đã convert.

### infun — nền đã có

- `spatie/laravel-permission` **đã cài thật** (không phải nhánh demo), bảng
  `sp_roles/sp_permissions/sp_model_has_roles/sp_model_has_permissions/
  sp_role_has_permissions` (đổi tên qua `config/permission.php`).
  `CmsPermission` middleware + `BaseCmsController::$permission` +
  `Route::cmsApiResource` đã chạy thật cho category/banner/product/blog/
  menu/order/order-status/carrier/payment/zone/district/ward.
- `Database\Seeders\SpatiePermissionSeeder` chỉ import **1 lần** từ bảng
  `permissions` cũ (mt219) sang `sp_permissions` — **không phải cơ chế tự
  sinh liên tục**. Entity mới không có sẵn quyền trừ khi thêm tay (tiền lệ:
  `list/detail/create/edit/del-menu-value` đã phải chèn thủ công).
- `UserGroupRepositoryInterface` mới có `listWithDescription()` (đọc).
  `UserRepositoryInterface` chỉ phục vụ storefront (register/profile/
  password) + `searchForCms()` (dropdown Author cho blog). Chưa có
  `RoleRepositoryInterface`/`PermissionRepositoryInterface`.
- `app/Http/Controllers/Api/Cms/System/` đã có (rỗng, chỉ `.gitkeep`) — theo
  `Api/Cms/README.md`, đây là nơi controller Role/User/Permission/Setting sẽ
  nằm khi build (`Setting` tạm để ở gốc, dời sau).

### infuncms — nền đã có

- `router/index.jsx` (`CRUD_ENTITIES`) + `menuConfig.js` đã khai `role`,
  `user`, `user-group` (nhóm menu "Users") — route/menu tồn tại nhưng
  **chưa có file `src/pages/role|user|userGroup` nào** → render placeholder.
- `routes/rcms.php` chỉ có `GET /user` (dropdown Author) — chưa route CRUD
  nào cho cả 3 entity.
- Login (`AuthController::resolvePermissions()` → `pages/auth/login.jsx`)
  **đã** lưu mảng permission vào `localStorage[CONSTANTS.PERMISSIONS]`
  (JSON string) sau khi đăng nhập — nhưng **chưa ai đọc lại**: không có
  store selector, không có hook, `MenuLeft.jsx`/`RequireAuth.jsx` không lọc
  gì theo quyền cả. Dữ liệu có sẵn, chỉ chưa dùng.

---

## Phase 0 — Bắt buộc trước, ảnh hưởng cả entity đã live

> **0.1 và 0.2 ĐÃ CODE xong (2026-08-03)** — xem diff `User.php`,
> `SettingController.php`, `routes/rcms.php`, migration
> `2026_08_03_000003_seed_setting_cms_permissions.php`. Còn đợi
> `php artisan migrate` + restart `infun-php` (RoadRunner giữ app boot
> trong memory, đổi code/route không tự nhận) rồi verify qua browser thật
> mới coi là xong. 0.3 (chống leo thang/tự khoá) vẫn pending — logic đó
> sống trong `RoleWriteService` chưa tồn tại (Phase 2).

### 0.1. Bug: `User::roles()`/`permissions()` che mất trait spatie

`app/Models/Entities/User.php` vừa `use HasRoles` (spatie) vừa tự khai:

```php
public function roles()       // trỏ bảng LEGACY role_user, model LEGACY App\Models\Entities\Role
public function permissions() // trỏ bảng LEGACY permission_user, model LEGACY App\Models\Entities\Permission
```

PHP: method khai trực tiếp trong class **luôn thắng** method cùng tên từ
trait — `Spatie\Permission\Traits\HasRoles::roles()` và
`HasPermissions::permissions()` bị che hoàn toàn, không bao giờ chạy được.
Xác nhận bằng cách đọc `config/permission.php`:
`models.role = Spatie\Permission\Models\Role::class` (bảng `sp_roles`),
trong khi `User::roles()` tự khai trỏ `role_user` (bảng KHÔNG có cột
`model_type`, khác hẳn shape `morphToMany` spatie cần).

**Hệ quả**: `$user->assignRole()`, `hasRole()`, `getAllPermissions()`
(đang dùng ở `AuthController::resolvePermissions()`), và mọi
`Gate::authorize()` qua `CmsPermission` middleware **đều thao tác nhầm
quan hệ**. Rất có thể toàn bộ entity đã bọc `cms.permission` (category,
banner, blog, menu, order...) đang phân quyền sai lệch ngay bây giờ.

**Việc cần làm:**

1. Đổi tên 2 method legacy trên `User` thành `legacyRoles()` /
   `legacyPermissions()` (hoặc xoá hẳn nếu grep không còn caller nào —
   `grep -rn "->roles()\|->permissions()" app resources` để rà trước).
2. Giữ lại bảng/model legacy (`App\Models\Entities\Role`, `Permission`,
   `RoleUser`, `PermissionRole`) — KHÔNG xoá ở phase này, chỉ hết bị che.
   Quyết định xoá hẳn để ở Phase 4 (dọn rác) sau khi chắc chắn migrate xong
   dữ liệu (xem 0.3).
3. Test bắt buộc trước khi merge:
   ```php
   $user = User::factory()->create();
   $user->assignRole('admin');
   expect($user->hasRole('admin'))->toBeTrue();
   expect($user->can('list-category'))->toBeTrue(); // nếu role admin có quyền này
   ```
4. Chạy lại `SpatiePermissionSeeder`, xác nhận `sp_model_has_roles` có đúng
   1 row cho user test (trước đó rất có thể insert nhầm vào `role_user` và
   throw SQL error vì thiếu cột `model_type`/`model_id`).
5. Test tay qua browser: đăng nhập tài khoản KHÔNG có quyền `edit-category`,
   gọi `PUT /rcms/category/{id}` → phải nhận 403. Đây là bằng chứng thực tế
   permission đang hoạt động đúng, không chỉ đúng trên giấy.

### 0.2. Setting đang không có phân quyền

`setting` nằm trong `Permissions::$_excepts` — route `GET/PUT /rcms/setting`
hiện KHÔNG bọc `cms.permission`, nghĩa là **mọi tài khoản CMS đăng nhập
được** đều sửa được maintenance mode, tỉ lệ hoa hồng affiliate, tỉ lệ quy
đổi điểm thưởng, xoá cache — đây là màn nhạy cảm nhất hệ thống mà lại là
màn duy nhất không gác.

**Việc cần làm:**
1. Thêm quyền `detail-setting` (GET) và `edit-setting` (PUT) vào registry
   (xem Phase 1), seed cho role admin.
2. Bọc `cms.permission` cho 2 route setting trong `routes/rcms.php`. Vì
   `SettingController` hiện không `extends BaseCmsController`, cần thêm
   `protected string $permission = 'setting';` + đổi `extends Controller`
   → `extends BaseCmsController`, HOẶC gọi `Gate::authorize()` thủ công
   trong từng method (đơn giản hơn, ít rủi ro đổi hành vi các chỗ khác đang
   gọi `SettingController`).
3. Cân nhắc tách quyền `edit-setting-finance` riêng cho nhóm field
   reward/affiliate (tab "Reward & Affiliate" + `config_affiliate_*`/
   `config_reward_*`) nếu muốn nhân sự vận hành thường không đụng được vào
   cấu hình tài chính — KHÔNG bắt buộc phase này, ghi lại làm follow-up.
4. `setting/del` (xoá cache) giữ nguyên không cần quyền riêng — ít rủi ro,
   không đổi dữ liệu nghiệp vụ.

### 0.3. Chống leo thang đặc quyền + tự khoá (mt219 không có)

mt219 chỉ chặn xoá chính user đang đăng nhập
(`UserController::_ignoreDelete`). Cần thêm 2 luật infun chưa có:

1. **Chống tự khoá**: không cho gỡ role cuối cùng của chính mình, không cho
   xoá/sửa role mà chính mình đang thuộc nếu sau khi sửa mình mất quyền
   `edit-role` (tự cắt đường vào lại).
2. **Chống leo thang**: khi lưu Role, user hiện tại không được tick quyền
   mà bản thân KHÔNG có. Không có luật này thì bất kỳ ai có `edit-role` là
   super-admin trên thực tế (tự cấp cho mình mọi quyền còn lại).

Đặt logic ở `RoleWriteService` (Phase 2), không đặt trong Controller.

---

## Phase 1 — Permission registry (khai báo, không quét runtime)

> **ĐÃ CODE xong (2026-08-04)** — xem `app/Enums/CmsPermissionEntity.php`
> (20 entity: 18 controller thật + `role`/`user-group` khai trước cho
> Phase 2), `app/Console/Commands/PermissionSyncCommand.php`
> (`permission:sync` + `permission:sync --check`), `docker/php/
> entrypoint.staging.sh` (chèn sync sau migrate), `.github/workflows/
> quality.yml` (job `permission-registry` mới), `tests/Feature/
> PermissionSyncCommandTest.php`. Chưa chạy thật trên container (không có
> PHP trong sandbox code — chỉ review tay) — cần user chạy
> `php artisan permission:sync` + `composer test` (hoặc `php artisan test
> --filter=PermissionSyncCommandTest`) trên `infun-php` rồi báo lại kết quả
> mới coi Phase 1 là xong.
>
> ⚠️ **SỰ CỐ 2026-08-04**: bản đầu của `PermissionSyncCommandTest.php` gọi
> thẳng `down()` của migration `2026_06_21_024339_create_permission_tables
> .php` ở `tearDown()` — Schema::drop() cả 5 bảng spatie thật khi test lỡ
> chạy nhắm vào DB thật thay vì sqlite `:memory:` (nghi do container
> `infun-php` đã có `bootstrap/cache/config.php` — `config:cache` chạy
> không điều kiện lúc entrypoint start — đóng băng `DB_CONNECTION=mysql`,
> khiến override `<env>` trong `phpunit.xml` bị bỏ qua khi chạy
> `php artisan test` KHÔNG qua `composer test`, script đó tự chạy
> `config:clear` trước). Đã vá: `assertSafeTestDatabase()` chặn cứng ở CẢ
> `setUp()` lẫn `tearDown()`, throw `RuntimeException` ngay nếu connection
> không đúng `sqlite`/`:memory:`. **Luật rút ra cho MỌI test tương lai chạy
> trên container đã qua entrypoint.staging.sh**: luôn `php artisan
> config:clear` trước `php artisan test` nếu không đi qua `composer test`;
> test nào có Schema::drop/truncate ngoài RefreshDatabase chuẩn PHẢI tự
> assert connection an toàn trước khi chạy, không tin `phpunit.xml` một
> mình là đủ trên container có config cache. Nếu xác nhận đã mất dữ liệu
> thật, khôi phục lại 5 bảng bằng `up()` của đúng migration trên rồi chạy
> lại `SpatiePermissionSeeder` + `permission:sync`.

mt219 suy quyền từ tên controller lúc runtime; infun hiện seed 1 lần rồi
thêm tay — cả hai đều trôi theo thời gian. Chọn giữa đường: **khai báo
tường minh trong code, đồng bộ bằng command idempotent**, nguồn slug lấy từ
`BaseCmsController::$permission` (đã là nguồn sự thật thật sự, chính xác
hơn suy từ tên class).

1. `app/Enums/CmsPermissionEntity.php` (hoặc `config/cms-permissions.php`)
   — liệt kê toàn bộ slug entity đang có `$permission` trong
   `Api/Cms/*Controller.php` (`category`, `banner`, `product`, `blog`,
   `menu`, `menu-value`, `order`, `order-status`, `carrier`, `payment`,
   `zone`, `district`, `ward`, `setting`, và 3 slug mới `role`, `user`,
   `user-group`). Actions cố định `list/detail/create/edit/del` (giữ đúng
   naming mt219 — đừng đổi sang `category.list` kiểu wildcard ở phase này,
   xem Phase 5).
2. `php artisan permission:sync` — idempotent, `Permission::findOrCreate()`
   cho từng `{action}-{entity}`, KHÔNG xoá quyền thừa tự động (chỉ warn) để
   tránh mất dữ liệu nếu ai đó xoá nhầm 1 dòng registry.
3. `php artisan permission:sync --check` — exit code khác 0 nếu có entity
   trong `Api/Cms/*Controller.php` (`$permission` property, quét bằng
   reflection hoặc scan file) mà chưa có trong registry. Thêm vào
   `.github/workflows/quality.yml` (cạnh `composer boundary` đã có) — quên
   khai quyền khi thêm entity mới thì CI đỏ ngay, không phải đợi phát hiện
   lúc runtime như mt219.
4. Entrypoint deploy (`docker/php/entrypoint.staging.sh`) chạy
   `permission:sync` ngay sau `migrate`, cùng chỗ đang chạy `route:cache`
   (đúng thứ tự: migrate → permission:sync → config:cache → route:cache).
5. Test: mọi entity đăng ký qua `cmsApiResource` phải có đủ 5 mã quyền
   trong `sp_permissions` sau khi chạy sync.

---

## Phase 2 — Backend CRUD (theo đúng mẫu `SettingController` đã build)

> **ĐÃ CODE xong (2026-08-04)** — cả 3 mục 2.1/2.2/2.3. Quyết định Phase 6 đã
> chốt: 6.1 = Chỉ Admin, 6.2 = Multi-role. Danh sách file chính:
> `app/Repositories/{Interfaces,Eloquent}/{UserGroup,Role,User}Repository*.php`,
> `app/Services/Cms/RoleWriteService.php` (+ 2 exception
> `RoleSelfLockoutException`/`RoleInUseException`), `app/Data/Cms/
> {UserGroup,UserGroupDescription,Role,User}Data.php`, `app/Http/Requests/Cms/
> {UserGroup,Role,User}Request.php`, `app/Http/Controllers/Api/Cms/System/
> {UserGroupController,RoleController}.php`, `app/Http/Controllers/Api/Cms/
> UserController.php` (MỞ RỘNG file cũ, không tạo mới — xem lý do trong
> docblock class), `app/Http/Middleware/CmsPermission.php` (thêm map
> `adminList`/`permissionsRegistry`), `routes/rcms.php`.
>
> Lưu ý khi verify/deploy (không có bước nào ghi/xoá DB — Phase 2 KHÔNG cần
> migration mới, `role`/`user-group`/`user` đã có sẵn quyền từ Phase 1):
> 1. `bootstrap/cache/repositories.php` (nếu đang tồn tại trên container) có
>    thể CHƯA có binding cho `RoleRepositoryInterface` (interface mới hoàn
>    toàn) — chạy `php artisan repository:clear` (an toàn, chỉ xoá 1 file
>    cache, tự sinh lại) hoặc restart container (entrypoint đã
>    `optimize:clear` mỗi lần start, tự self-heal qua discover).
> 2. `php artisan permission:sync --check` nên PASS ngay (role/user-group đã
>    đăng ký ở Phase 1, user đã có sẵn từ trước) — chạy để xác nhận không có
>    gì lệch.
> 3. Role: `destroy()` là XOÁ CỨNG (spatie Role không có `deleted_at`) — CHỈ
>    test trên role tạo riêng để thử, TUYỆT ĐỐI không xoá thử role `admin`
>    hay role đang gán cho ai (dù đã có guard `RoleInUseException`, xem quy
>    tắc an toàn ở docs/CLAUDE.md — vẫn phải tự cẩn thận, guard có thể có bug
>    chưa lường hết).
> 4. Frontend (Phase 3) CHƯA làm — đây thuần backend API, chưa có UI nào gọi
>    tới các endpoint mới.
> 5. Review bằng mắt (không có PHP trong sandbox code) — CHƯA chạy thật, cần
>    verify qua Postman/Chrome (gọi trực tiếp API vì chưa có UI) trước khi
>    coi Phase 2 là xong.

Tham khảo trực tiếp: `app/Http/Controllers/Api/Cms/SettingController.php`,
`ResourceController.php` (dropdown pattern), `BannerController.php`
(cmsApiResource pattern đầy đủ nhất hiện có).

### 2.1. UserGroup — đơn giản, làm trước để có form mẫu

- Thêm `create/update/delete` vào `UserGroupRepositoryInterface` (hiện chỉ
  đọc) + implement ở `UserGroupRepository`.
- `App\Http\Controllers\Api\Cms\System\UserGroupController extends
  BaseCmsController` (`$permission = 'user-group'`) + `Route::cmsApiResource`.
- DTO `app/Data/Cms/UserGroupData.php` (snake_case, theo convention CMS DTO
  đã ghi trong `docs/CLAUDE.md` mục "CMS REST API").

### 2.2. Role — phần khó nhất, phụ thuộc Phase 0 + Phase 1

- `RoleRepositoryInterface` + `RoleRepository extends QueryableRepository`.
- `App\Services\Cms\RoleWriteService` (theo `CMS-MODULE-BOUNDARY.md`: đây
  đúng loại orchestration "không đụng nghiệp vụ tính tiền/kho" nên được
  phép đặt ở `app/Services/Cms/`) — chứa:
  - `save(?Role, array $roleData, array $permissionIds)` — transaction:
    upsert role, `syncPermissions()` (spatie built-in, KHÔNG tự viết lại
    delete+insert như mt219).
  - Luật chống leo thang từ 0.3: filter `$permissionIds` theo
    `auth()->user()->getAllPermissions()` trước khi sync.
  - Luật chống tự khoá: reject nếu role đang sửa là role hiện tại của user
    VÀ sau khi sync user mất `edit-role`.
- `GET /rcms/role/{id}/permissions` (hoặc gộp vào `show`) — trả 2 phần:
  danh sách permission registry (Phase 1) group theo entity, và permission
  hiện tại của role (`$role->permissions->pluck('id')`) — thay thế
  `_getRolePermission()` runtime-scan của mt219 bằng đọc registry tĩnh.
- `App\Http\Controllers\Api\Cms\System\RoleController extends
  BaseCmsController` (`$permission = 'role'`).

### 2.3. User (CMS admin) — xem quyết định kiến trúc ở Phase 6 trước khi làm

Phase này **chặn bởi 1 quyết định chưa chốt** (xem Phase 6.1: gộp hay tách
Admin/Member). Tạm thời implement theo hướng "chỉ quản trị viên" (an toàn
hơn, khớp `CMS-MODULE-BOUNDARY.md` — System group nên chỉ chứa admin), nếu
sau này cần quản Member trong CMS thì đó là màn Customer riêng, không đụng
lại code này.

- `UserRepositoryInterface` thêm: `listForCms()` (paginate, filter
  `type=1`), `createAdmin()`, `updateAdmin()` — KHÔNG đụng các method
  storefront hiện có.
- Password: hash ở service layer, không bao giờ trả `password` ra DTO
  (đã có `protected $hidden = ['password', ...]` trên model, nhưng DTO CMS
  tự map tay nên vẫn phải cố ý loại trừ field này).
- Gán role: `$user->syncRoles([...])` — xem 6.2 (single vs multi-role).

---

## Phase 3 — Frontend: 3 trang React

Theo đúng khung `pages/setting/detail.jsx` + `components/setting/*.jsx` đã
làm (nav-tabs bootstrap cũ, `api.js`, `useLoading`, `confirm/success/error`
từ `alert.js`), và khung list/form đã convert ở `category`/`blog`/`menu`
cho phần CRUD chuẩn.

### 3.1. `pages/userGroup/{index,form}.jsx` — làm trước, đơn giản nhất
Sao chép form pattern của `category/form.jsx` (đa ngôn ngữ theo
`useListLanguages()`), field `approval` + `sort_order`.

### 3.2. `pages/role/{index,form}.jsx` — ma trận quyền
- `index.jsx`: list chuẩn (giống `category/index.jsx`).
- `form.jsx` + `components/role/PermissionMatrix.jsx`: bảng hàng = entity
  (group theo 6 nhóm nghiệp vụ trong `Api/Cms/README.md`: Catalog/Order/
  Customer/Marketing/Content/System — KHÔNG liệt kê phẳng ~20 entity như
  mt219, sẽ phình tới ~90 khi backend làm đủ), cột = 5 action, check-all
  theo CẢ hàng lẫn cột (mt219 chỉ có theo cột). Thêm ô tìm kiếm lọc theo
  tên entity.
- Component `<Can permission="edit-role">` (xem 3.4) bọc quanh checkbox
  không thuộc quyền hiện có của user đăng nhập — disable, không phải ẩn,
  để user hiểu vì sao không tick được (khớp luật chống leo thang ở backend).

### 3.3. `pages/user/{index,form}.jsx`
Theo quyết định Phase 6 — mặc định implement bản "chỉ Admin". Field: avatar
(`Photo.jsx` có sẵn), username, email, full_name, password (input show/hide
giống mt219), role (Select — single hoặc multi tuỳ 6.2), status.

### 3.4. Thực thi quyền ở FE — hiện đang trống hoàn toàn

`localStorage[CONSTANTS.PERMISSIONS]` đã có data từ lúc login, chỉ chưa ai
đọc. Cần thêm:

1. `core/stores/userStore.js` — thêm `permissions: []` vào state, load từ
   `localStorage` lúc init (giống pattern `currentUser` đã có), setter
   `setPermissions` gọi trong `login.jsx` cạnh chỗ đang
   `localStorage.setItem(CONSTANTS.PERMISSIONS, ...)`.
2. `core/hooks/usePermission.js` — `const can = usePermission();
   can('edit-category')`.
3. `components/ui/Can.jsx` — `<Can permission="edit-category">
   <Button>Sửa</Button></Can>`, render null nếu không có quyền. Dùng ở mọi
   nút Thêm/Sửa/Xoá của MỌI trang đã convert (category/banner/product/
   blog/menu/order + 3 trang mới) — phạm vi rộng hơn 3 trang trong doc này,
   ghi rõ để không làm nửa vời (làm `Can` mà chỉ áp cho role/user/userGroup
   thì các trang cũ vẫn lộ nút không có quyền).
4. `menuConfig.js` filter theo quyền (ẩn menu con không có quyền `list-*`,
   ẩn luôn nhóm cha khi rỗng hết con).
5. `RequireAuth.jsx` — tuỳ chọn thêm kiểm tra quyền theo route (chặn gõ
   thẳng URL), redirect `/permission-denied` (đã có sẵn page placeholder).

Backend (`cms.permission` middleware) vẫn là nguồn quyết định thật — phần
FE này thuần UX, không thay thế được kiểm tra ở Phase 0/2.

> **Trạng thái Phase 3 (2026-08-05, verify xong qua Chrome trên infuncms
> thật — cms.infun.co):**
>
> Trước khi làm Phase 3, phát hiện + fix 1 bug thật ở Phase 2:
> `GET /rcms/role` 500 "Class name must be a valid object or a string".
> Nguyên nhân: `RoleRepository::listForCms()` dùng `Role::query()` trần —
> constructor spatie tự suy `guard_name` qua `Guard::getDefaultName()` đọc
> `config('auth.defaults.guard')`. Middleware `auth:sanctum` sau khi xác
> thực gọi `Auth::shouldUse('sanctum')` → ghi đè config đó thành `'sanctum'`
> cho HẾT phần còn lại của request → model `Role` trần dựng lúc
> `withCount(['users'])` bị gán nhầm `guard_name='sanctum'` →
> `Role::users()` gọi `getModelForGuard('sanctum')` → null → `morphedByMany
> (null,...)` → lỗi trên. CLI (tinker) không lộ bug vì không qua
> `auth:sanctum`. Fix: `(new Role(['guard_name' => self::GUARD]))
> ->newQuery()` thay vì `Role::query()` — truyền thẳng guard_name, bỏ qua
> suy đoán. Đã re-verify cả 4 endpoint Phase 2 (role/user-group/user
> admin-list/user dropdown cũ) → 200 OK.
>
> Đã làm (files mới/sửa, infuncms):
> - `core/hooks/usePermission.js`, `components/ui/Can.jsx` — plumbing chung.
> - `core/stores/userStore.js` — thêm state `permissions` + `setPermissions`,
>   đọc lại từ `localStorage` lúc khởi động (khớp pattern `currentUser`).
> - `pages/auth/login.jsx` — gọi `setPermissions()` thay vì tự ghi
>   `localStorage` tay.
> - `core/services/api.js` — thêm nhánh 403 → redirect `/permission-denied`
>   (khác 401: KHÔNG xoá phiên đăng nhập).
> - `components/app/menuConfig.js` + `MenuLeft.jsx` — thêm field
>   `permission` cho item khớp `CmsPermissionEntity` đã đăng ký, ẩn theo
>   `usePermission()`. Item KHÔNG có `permission` LUÔN hiện (an toàn hơn ẩn
>   oan — nhiều entity cũ chưa vào registry).
> - `pages/userGroup/{index,form}.jsx` — mirror `category` 1:1.
> - `pages/role/{index,form}.jsx` + `components/role/PermissionMatrix.jsx`
>   — ma trận quyền group theo 6 nhóm nghiệp vụ (`Api/Cms/README.md`),
>   check-all hàng+cột, ô tìm kiếm, disable (không ẩn) checkbox ngoài quyền
>   hiện có của user đăng nhập.
> - `pages/user/{index,form}.jsx` — multi-role Select (Phase 6.2), password
>   show/hide, để trống lúc sửa = giữ nguyên.
>
> Cố ý CHƯA làm (không phải quên — phạm vi cắt có chủ đích, xem lý do):
> - **Chưa gắn `<Can>` vào nút Thêm/Sửa/Xoá của các trang CŨ** (category/
>   banner/product/blog/menu/order...) — plan gốc mục 3.4 có nói phạm vi này
>   rộng hơn 3 trang mới, nhưng sửa ~10 trang đang chạy thật cùng lúc không
>   có trong yêu cầu gốc của đợt này — để riêng, làm khi có dịp chạm vào
>   từng trang.
> - **`RequireAuth.jsx` chưa chặn theo quyền per-route** (mục 3.4.5, ghi rõ
>   "tuỳ chọn") — hiện chỉ chặn theo đăng nhập, gõ thẳng URL không có quyền
>   vẫn vào được trang (nhưng gọi API sẽ 403 → giờ tự bật `/permission-denied`
>   nhờ interceptor mới thêm). Backend `cms.permission` middleware vẫn là
>   nguồn chặn thật.
> - Menu chỉ gắn `permission` cho ~18/50 item khớp entity đã có trong
>   `CmsPermissionEntity` — item còn lại (product, coupon, voucher...) chưa
>   được backend gate nên KHÔNG gắn (gắn nhầm sẽ ẩn oan mục đó với mọi user
>   kể cả admin, vì permission đó chưa tồn tại trong DB).
>
> Verify qua Chrome (không có bước ghi/xoá DB nào ngoài phạm vi user tự bấm
> Save trên UI — chưa test write thật, chỉ xác nhận render + load dữ liệu
> đúng): `role/list`, `role/1` (ma trận quyền, tick đúng theo 255 quyền của
> admin), `user-group/list`, `user/list`, `user/1` (multi-role tag "admin"
> hiện đúng) — cả 5 trang render đúng dữ liệu thật, 0 console error. Lưu ý
> phát hiện thêm: `localStorage[PERMISSIONS_CMS]` của phiên đăng nhập CŨ
> (trước khi Phase 1/2 build xong) chỉ có 4 quyền — phải đăng nhập lại (hoặc
> đợi lần login kế tiếp) để menu "Users" hiện đúng; đây là hành vi đúng theo
> thiết kế (permissions chỉ refresh lúc login), không phải bug.

---

## Phase 4 — Dọn rác (sau khi Phase 0-3 chạy ổn định trong ít nhất 1 tuần)

- Xoá `App\Models\Entities\Role`, `Permission`, `RoleUser`, `PermissionRole`
  + bảng `roles/permissions/role_user/permission_role` NẾU grep xác nhận
  không còn caller nào (sau khi đổi tên ở 0.1, các method `legacyRoles()`/
  `legacyPermissions()` nên không còn ai gọi).
- Xoá bảng `permissions` cũ (nguồn import 1 lần của `SpatiePermissionSeeder`)
  SAU KHI Phase 1 (registry) đã thay thế hoàn toàn vai trò nguồn sự thật.

> **Trạng thái (2026-08-05):** Chạy theo yêu cầu tường minh của user ngay
> trong ngày Phase 3 xong — **KHÔNG đợi đủ ≥1 tuần ổn định như khuyến nghị
> gốc ở trên**. Đã audit trước (grep toàn repo `infun`, loại vendor/
> node_modules): 0 caller còn lại cho `App\Models\Entities\{Role,Permission,
> RoleUser,PermissionRole}` và `User::legacyRoles()/legacyPermissions()`
> (ngoài chính chúng); `database/seeders/SpatiePermissionSeeder.php` là nơi
> DUY NHẤT còn đọc bảng `permissions` cũ, không có entrypoint/CI nào tự
> chạy seeder này. 2 migration seed quyền CMS gần đây (warehouse/setting)
> chỉ đụng bảng `sp_*`, không liên quan 5 bảng legacy.
>
> Đã làm:
> - Xoá `app/Models/Entities/{Role,Permission,RoleUser,PermissionRole}.php`,
>   `database/seeders/SpatiePermissionSeeder.php`, method
>   `legacyRoles()`/`legacyPermissions()` trong `User.php` — an toàn/hoàn
>   tác được qua git (không đụng DB).
> - Viết migration `2026_08_05_000000_drop_legacy_role_permission_tables.php`
>   — `Schema::dropIfExists` cho `role_user/permission_user/permission_role/
>   roles/permissions`. **CHƯA CHẠY** — `down()` KHÔNG khôi phục được dữ
>   liệu (5 bảng này chưa từng có migration CREATE gốc trong repo, import
>   1 lần từ DB mt219). Đây là bước **KHÔNG THỂ HOÀN TÁC** — dừng lại nếu
>   chưa chắc chắn, restore từ DB backup là cách duy nhất nếu cần dữ liệu
>   lại sau khi chạy.
>
> **User đã chạy migration, verify xong — nhưng phát hiện thêm 1 bug thật
> do CHÍNH đợt dọn rác này gây ra** (lộ ra khi rà lại code trước khi build
> Customer, chưa kịp có ai đụng phải): `App\Models\Entities\User` vẫn còn 3
> method `roleUser()`/`roleUsers()`/`roleUsers2()` tham chiếu
> `RoleUser::class` (cùng namespace nên không cần `use`, grep audit ban đầu
> lọt mất) — VÀ `roleUsers2` còn nằm trong `$destroyRelations`, nghĩa là
> `HasCascadeRelations::bootHasCascadeRelations()` (hook vào event
> `deleting`) gọi `$user->roleUsers2()` MỖI LẦN xoá (mềm hay cứng) BẤT KỲ
> User nào → fatal `Class "RoleUser" not found` vì file đã xoá. Tức là sau
> khi drop bảng, xoá bất kỳ user nào (Admin lẫn Customer sắp làm) sẽ crash.
> Đã fix: xoá 3 method đó + bỏ `'roleUsers2'` khỏi `$destroyRelations`. Đã
> verify thật: tạo 1 admin test throwaway qua `POST /rcms/user` rồi
> `DELETE /rcms/user/{id}` → 204 OK, không còn crash.

---

## Phase 5 — Tuỳ chọn, cân nhắc sau (không làm chung đợt này)

- **`Gate::before` cho role `admin`** — bypass mọi check, khỏi phải đồng bộ
  quyền mỗi lần thêm entity mới. Rẻ, không cần migrate dữ liệu, nhưng làm
  loãng ý nghĩa "admin cũng chỉ là 1 role có đủ quyền" — cân nhắc kỹ trước
  khi thêm, có thể bỏ qua nếu Phase 1 (CI check) đã đủ để không quên seed.
- **Đổi format `category.list` (wildcard spatie)** — lợi ích thật (`can
  ('category.*')`) nhưng phải migrate toàn bộ `sp_permissions` hiện có +
  sửa `CmsPermission` map action. Không làm chung đợt.
- **Audit log** (`spatie/laravel-activitylog`) — ai đổi quyền của ai, lúc
  nào. Đáng làm sớm vì hệ đã có tiền (affiliate, reward, voucher) nhưng là
  1 package + observer riêng, tách đợt.
- **IP allowlist/VPN cho `/rcms`** — đã nằm trong TODO của
  `CMS-MODULE-BOUNDARY.md` mục 5, bổ trợ tốt cho Phase 0.2 (Setting).

---

## Phase 6 — Quyết định cần chốt TRƯỚC khi làm Phase 2.3/3.3 (User)

Đây là 2 quyết định duy nhất trong toàn bộ plan chưa có câu trả lời rõ —
Claude Code KHÔNG tự quyết, phải hỏi lại người yêu cầu task trước khi code
Phase 2.3/3.3:

### 6.1. Màn "User" trong CMS quản ai?
- **(a) Chỉ Admin** (khuyến nghị) — khớp `CMS-MODULE-BOUNDARY.md` (System
  group = "user quản trị"), Member đã có domain Customer/Account riêng
  (`AccountService`, `AuthController` web). Gộp chung như mt219 sẽ tạo 1
  form vừa quản nhân viên vừa quản khách hàng, lệch ranh giới đã chốt.
- **(b) Gộp Admin + Member như mt219** — giữ nguyên UX cũ, nhưng phải làm
  thêm nhánh Member (phone/address/sex/newsletter/user_group_id) mà
  storefront domain đã có sẵn cách quản lý riêng — trùng lặp.

### 6.2. User — 1 role hay nhiều role?
- **(a) Multi-role** (khuyến nghị) — spatie hỗ trợ sẵn (`syncRoles`), chi
  phí FE gần bằng 0 (Select `mode="multiple"` thay vì single, đã dùng
  pattern này ở `setting` cho `category_show_home_page`).
- **(b) Single-role như mt219** — giữ đúng UX cũ, đơn giản hơn khi audit
  "user này có quyền gì" (nhìn 1 role duy nhất).

---

## Thứ tự thực hiện đề xuất

```
Phase 0 (bug fix + gác Setting)  →  verify qua browser thật
Phase 1 (registry + CI check)
Phase 2.1 (UserGroup backend)    →  Phase 3.1 (UserGroup FE)   →  verify
Phase 6 (chốt 2 quyết định User)
Phase 2.2 (Role backend)         →  Phase 3.2 (Role FE)        →  verify
Phase 2.3 (User backend)         →  Phase 3.3 (User FE)        →  verify
Phase 3.4 (FE permission gating — áp cho TẤT CẢ trang đã convert)
Phase 4 (dọn rác, sau ≥1 tuần ổn định)
Phase 5 (tuỳ chọn, không bắt buộc)
```

Phase 0 tách riêng 1 PR/đợt nhỏ, review kỹ và test tay trên browser trước
khi merge — nó động vào phân quyền của category/banner/product/blog/menu/
order đang chạy thật, không chỉ 3 entity sắp thêm.
