<?php

namespace Tests\Unit\Architecture;

use Tests\TestCase;

/**
 * Guardrail kiến trúc cho repository dùng shared `$this->model`.
 *
 * Chặn vĩnh viễn 2 kiểu bug đã bàn:
 *
 *  1. GÁN LẠI `$this->model` (ngoài makeModel) — kiểu l5-repository tích luỹ
 *     where/scope lên `$this->model` khiến query rò sang lần gọi sau nếu quên
 *     resetModel(). Repo phải build query trên biến cục bộ (newQuery()/::query()).
 *
 *  2. MUTATE ATTRIBUTE trên instance dùng chung (`$this->model->fill/save/...`) —
 *     làm instance chung mang attribute cũ → leak sang lần gọi sau, và leak GIỮA
 *     REQUEST khi chạy Octane với binding singleton. Ghi dữ liệu phải bằng model
 *     cục bộ: `new Model()` / `Model::create(...)`.
 *
 * Test quét tĩnh source trong app/Repositories, không chạm DB.
 */
class RepositoryModelStateTest extends TestCase
{
    /** @return array<string, string> path => nội dung file (đã bỏ NUL padding) */
    private function repositoryFiles(): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(app_path('Repositories'), \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $files[$file->getPathname()] = str_replace("\0", '', (string) file_get_contents($file->getPathname()));
        }

        return $files;
    }

    private function relative(string $path): string
    {
        return str_replace(base_path() . DIRECTORY_SEPARATOR, '', $path);
    }

    private function lineAt(string $code, int $offset): int
    {
        return substr_count(substr($code, 0, $offset), "\n") + 1;
    }

    public function test_khong_gan_lai_this_model_ngoai_makeModel(): void
    {
        $offenders = [];

        foreach ($this->repositoryFiles() as $path => $code) {
            // "$this->model =" (assignment) nhưng KHÔNG phải "==", "===", "=>".
            if (! preg_match_all('/\$this->model\s*=(?![=>])\s*([^;]+);/', $code, $matches, PREG_OFFSET_CAPTURE)) {
                continue;
            }

            foreach ($matches[1] as $rhsMatch) {
                $rhs = trim($rhsMatch[0]);
                // Ngoại lệ DUY NHẤT: gán trong makeModel() → `$this->model = $model;`.
                if ($rhs === '$model') {
                    continue;
                }
                $offenders[] = $this->relative($path) . ':' . $this->lineAt($code, $rhsMatch[1])
                    . '  → $this->model = ' . $rhs;
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "Cấm gán lại \$this->model (chỉ makeModel được phép). Build query trên biến cục bộ:\n"
                . implode("\n", $offenders),
        );
    }

    public function test_khong_mutate_attribute_tren_this_model(): void
    {
        $banned = 'fill|forceFill|setAttribute|setRawAttributes|save|update|delete|increment|decrement';
        $offenders = [];

        foreach ($this->repositoryFiles() as $path => $code) {
            if (! preg_match_all('/\$this->model->(' . $banned . ')\s*\(/', $code, $matches, PREG_OFFSET_CAPTURE)) {
                continue;
            }

            foreach ($matches[0] as $hit) {
                $offenders[] = $this->relative($path) . ':' . $this->lineAt($code, $hit[1])
                    . '  → ' . trim($hit[0]);
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "Cấm mutate attribute trên \$this->model dùng chung. Ghi bằng new Model()/Model::create():\n"
                . implode("\n", $offenders),
        );
    }
}
