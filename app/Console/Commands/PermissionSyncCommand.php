<?php

namespace App\Console\Commands;

use App\Enums\CmsPermissionEntity;
use App\Models\Entities\Permission;
use Illuminate\Console\Command;
use Spatie\Permission\PermissionRegistrar;

class PermissionSyncCommand extends Command
{
    protected $signature = 'permission:sync
        {--check : Chỉ đối chiếu registry với controller trong code, KHÔNG ghi DB — dùng cho CI}';

    protected $description = 'Đồng bộ sp_permissions với registry App\\Enums\\CmsPermissionEntity (Phase 1 ROLE-PERMISSION-PLAN.md).';

    private const GUARD = 'web';

    public function handle(): int
    {
        $missingFromRegistry = $this->findControllerSlugsMissingFromRegistry();

        if ($this->option('check')) {
            return $this->runCheck($missingFromRegistry);
        }

        return $this->runSync($missingFromRegistry);
    }

    /**
     * Chế độ CI: KHÔNG mở DB connection nào — chỉ file scan + so sánh string,
     * an toàn chạy trong job không có service DB (xem quality.yml).
     */
    private function runCheck(array $missingFromRegistry): int
    {
        if ($missingFromRegistry !== []) {
            $this->components->error(
                'Controller khai $permission nhưng CHƯA có trong registry: ' . implode(', ', $missingFromRegistry),
            );
            $this->line('→ Thêm case tương ứng vào App\\Enums\\CmsPermissionEntity rồi chạy lại `php artisan permission:sync`.');

            return self::FAILURE;
        }

        $this->components->info(
            'OK — mọi controller đã khai $permission đều có trong registry (' . count(CmsPermissionEntity::cases()) . ' entity).',
        );

        return self::SUCCESS;
    }

    private function runSync(array $missingFromRegistry): int
    {
        if ($missingFromRegistry !== []) {
            $this->components->warn(
                'Controller sau khai $permission nhưng CHƯA có trong registry — SẼ KHÔNG có quyền nào được tạo cho chúng: '
                . implode(', ', $missingFromRegistry),
            );
            $this->line('→ Thêm case vào App\\Enums\\CmsPermissionEntity rồi chạy lại lệnh này.');
            $this->newLine();
        }

        $allCodes = CmsPermissionEntity::allPermissionCodes();
        $existingCodes = Permission::query()
            ->where('guard_name', self::GUARD)
            ->pluck('name')
            ->all();

        $toCreate = array_values(array_diff($allCodes, $existingCodes));

        foreach ($toCreate as $code) {
            Permission::findOrCreate($code, self::GUARD);
        }

        if ($toCreate !== []) {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }

        $this->components->info(sprintf(
            '%d quyền mới tạo, %d đã có sẵn (tổng %d mã trong registry).',
            count($toCreate),
            count($allCodes) - count($toCreate),
            count($allCodes),
        ));
        if ($toCreate !== []) {
            $this->line('  ' . implode(', ', $toCreate));
        }

        $this->warnStalePermissions($allCodes);

        return self::SUCCESS;
    }

    /**
     * Quyền đang có trong DB (guard 'web') nhưng KHÔNG thuộc bất kỳ entity
     * nào trong registry hiện tại — CHỈ CẢNH BÁO, không tự xoá (có thể là
     * quyền hợp lệ cho 1 entity chưa kịp thêm vào enum, hoặc quyền legacy
     * import từ mt219 không còn dùng — cần người xem lại bằng mắt).
     */
    private function warnStalePermissions(array $allCodes): void
    {
        $registrySet = array_flip($allCodes);

        $stale = Permission::query()
            ->where('guard_name', self::GUARD)
            ->pluck('name')
            ->reject(static fn (string $name): bool => isset($registrySet[$name]))
            ->values()
            ->all();

        if ($stale === []) {
            return;
        }

        $preview = array_slice($stale, 0, 20);
        $this->newLine();
        $this->components->warn(sprintf(
            '%d quyền có trong DB nhưng KHÔNG thuộc registry hiện tại (giữ nguyên, không tự xoá — kiểm tra lại xem có phải lỡ xoá nhầm 1 case trong CmsPermissionEntity, hay đây là quyền legacy/để-role-khác không liên quan CMS):',
            count($stale),
        ));
        $this->line('  ' . implode(', ', $preview) . (count($stale) > count($preview) ? ', … (+' . (count($stale) - count($preview)) . ' nữa)' : ''));
    }

    /**
     * Quét app/Http/Controllers/Api/Cms/**\/*.php tìm
     * `protected string $permission = '...'`, trả về slug nào KHÔNG có
     * trong CmsPermissionEntity — file scan thuần (không dùng reflection/
     * class_exists) để KHÔNG cần autoload/instantiate controller (có
     * constructor inject repository/service — reflection sẽ kéo theo cả
     * container resolve, dễ vỡ nếu thiếu binding lúc chạy --check).
     */
    private function findControllerSlugsMissingFromRegistry(): array
    {
        $declared = $this->scanControllerPermissionSlugs();
        $registered = array_flip(CmsPermissionEntity::slugs());

        return array_values(array_diff(array_keys($declared), array_keys($registered)));
    }

    /** @return array<string,string> slug => đường dẫn file khai báo */
    private function scanControllerPermissionSlugs(): array
    {
        $dir = app_path('Http/Controllers/Api/Cms');
        if (! is_dir($dir)) {
            return [];
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
        );

        $slugs = [];
        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $content = file_get_contents($file->getPathname());
            if ($content === false) {
                continue;
            }

            if (preg_match('/protected\s+string\s+\$permission\s*=\s*\'([^\']*)\'/', $content, $matches) !== 1) {
                continue;
            }

            $slug = $matches[1];
            if ($slug === '') {
                continue; // BaseCmsController mặc định '' — không phải entity thật.
            }

            $slugs[$slug] = $file->getPathname();
        }

        return $slugs;
    }
}
