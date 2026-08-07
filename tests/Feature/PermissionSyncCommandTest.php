<?php

namespace Tests\Feature;

use App\Enums\CmsPermissionEntity;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Test cho `php artisan permission:sync` (docs/ROLE-PERMISSION-PLAN.md Phase 1).
 *
 * KHÔNG dùng RefreshDatabase (chạy hết migration thật của app) — migration
 * `2026_05_29_000000_convert_legacy_tables_to_innodb` query
 * `information_schema.TABLES`, vỡ ngay trên sqlite :memory: (env testing,
 * xem phpunit.xml DB_CONNECTION=sqlite). Test này chỉ cần đúng 5 bảng
 * sp_permissions/sp_roles/... nên tự chạy TRỰC TIẾP migration thật của
 * spatie (`2026_06_21_024339_create_permission_tables.php` — an toàn
 * cross-DB, chính spatie có sẵn cờ `config('permission.testing')` riêng cho
 * sqlite), không phụ thuộc 75 migration còn lại.
 *
 * ⚠️ CẢNH BÁO AN TOÀN — SỰ CỐ THẬT (2026-08-04): tearDown() gọi
 * `down()` của migration trên → Schema::drop() CẢ 5 BẢNG SPATIE. Việc này
 * chỉ vô hại nếu connection đang trỏ đúng sqlite :memory: của test env. Nếu
 * config đã bị `artisan config:cache` "đóng băng" DB_CONNECTION=mysql (vd
 * container staging, entrypoint.staging.sh chạy config:cache không điều
 * kiện lúc start) rồi ai đó chạy `php artisan test` TRỰC TIẾP (bỏ qua bước
 * `artisan config:clear` mà script composer "test" làm hộ) — override
 * `<env>` trong phpunit.xml bị bỏ qua HOÀN TOÀN (config() đọc thẳng mảng đã
 * cache, không gọi lại env()), test này sẽ DROP bảng sp_permissions/sp_roles/
 * model_has_permissions/model_has_roles/sp_role_has_permissions THẬT — đã
 * xảy ra đúng 1 lần, mất sạch dữ liệu role/permission vừa seed ở Phase 0.
 * → assertSafeTestDatabase() bên dưới CHẶN CỨNG: abort ngay nếu connection
 * không phải sqlite :memory:, KHÔNG được xoá/nới lỏng guard này.
 */
class PermissionSyncCommandTest extends TestCase
{
    private const PERMISSION_MIGRATION = 'database/migrations/2026_06_21_024339_create_permission_tables.php';

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSafeTestDatabase();

        (require base_path(self::PERMISSION_MIGRATION))->up();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Lưới an toàn cứng — xem cảnh báo ở docblock class. KHÔNG dùng
     * assertion thường (có thể bị skip/suppress) — throw thẳng
     * RuntimeException để không cách nào tiếp tục xuống tới đoạn DROP bảng
     * nếu connection trông không giống DB test cô lập.
     */
    private function assertSafeTestDatabase(): void
    {
        $connection = config('database.default');
        $database   = (string) DB::connection()->getDatabaseName();

        if ($connection !== 'sqlite' || $database !== ':memory:') {
            throw new RuntimeException(
                'PermissionSyncCommandTest CHỈ được chạy khi DB_CONNECTION=sqlite và DB_DATABASE=:memory: '
                . "(hiện tại: connection=\"{$connection}\" database=\"{$database}\"). Test này DROP bảng "
                . 'sp_permissions/sp_roles/... thật ở tearDown() — dừng lại để không mất dữ liệu thật. '
                . 'Nguyên nhân thường gặp: config đã bị `artisan config:cache` đóng băng DB thật, chạy '
                . '`php artisan config:clear` trước (hoặc dùng `composer test` — script đó tự làm bước này), '
                . 'KHÔNG chạy `php artisan test` trực tiếp trên container đã config:cache production.',
            );
        }
    }

    protected function tearDown(): void
    {
        // Gọi LẠI guard ở đây, KHÔNG giả định setUp() đã chặn thành công —
        // 1 số phiên bản PHPUnit vẫn gọi tearDown() dù setUp() throw. Đây là
        // nơi thật sự chạy Schema::drop(), phải tự đứng vững một mình.
        $this->assertSafeTestDatabase();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        (require base_path(self::PERMISSION_MIGRATION))->down();

        parent::tearDown();
    }

    public function test_sync_creates_permission_codes_for_every_registry_entity(): void
    {
        $this->assertSame(0, Permission::count(), 'Bảng sp_permissions phải rỗng trước khi sync (fixture sạch).');

        $this->artisan('permission:sync')->assertExitCode(0);

        $expected = CmsPermissionEntity::allPermissionCodes();
        $this->assertSame(count($expected), Permission::count());

        foreach (CmsPermissionEntity::cases() as $entity) {
            foreach ($entity->permissionCodes() as $code) {
                $this->assertDatabaseHas('sp_permissions', [
                    'name'       => $code,
                    'guard_name' => 'web',
                ]);
            }
        }
    }

    public function test_sync_is_idempotent(): void
    {
        $this->artisan('permission:sync')->assertExitCode(0);
        $countAfterFirstRun = Permission::count();

        // Chạy lại — KHÔNG được tạo trùng, KHÔNG được lỗi (unique constraint
        // name+guard_name nếu code không kiểm tra tồn tại trước khi tạo).
        $this->artisan('permission:sync')->assertExitCode(0);

        $this->assertSame($countAfterFirstRun, Permission::count());
    }

    public function test_sync_does_not_delete_stale_permissions(): void
    {
        Permission::findOrCreate('edit-mon-hoc-khong-ton-tai', 'web');

        $this->artisan('permission:sync')->assertExitCode(0);

        // Cảnh báo, không xoá — permission "lạ" (không thuộc registry) vẫn
        // phải còn nguyên sau sync (xem PermissionSyncCommand::warnStalePermissions).
        $this->assertDatabaseHas('sp_permissions', [
            'name'       => 'edit-mon-hoc-khong-ton-tai',
            'guard_name' => 'web',
        ]);
    }

    /**
     * Đây chính là bài test mà CI (.github/workflows/quality.yml, job
     * permission-registry) chạy qua `--check` — đặt lại ở đây để chạy được
     * cả cục local (`composer test`), không chỉ trên CI.
     */
    public function test_check_passes_for_current_codebase_state(): void
    {
        $this->artisan('permission:sync', ['--check' => true])->assertExitCode(0);
    }
}
