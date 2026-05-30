<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Convert TOÀN BỘ bảng MyISAM trong DB sang InnoDB.
 *
 * Lý do:
 *  - MyISAM KHÔNG hỗ trợ Foreign Key, transaction, row-level locking,
 *    crash recovery — không phù hợp e-commerce hiện đại.
 *  - Cluster migration variant mới (product_variant + FK tới product, option,
 *    option_value) cần InnoDB hai phía.
 *  - Dù chỉ 3 bảng kia bắt buộc, convert toàn bộ tiện 1 lần — sau này muốn
 *    thêm FK ở quan hệ nào cũng được.
 *
 * Timestamp 2026_05_29 (sớm hơn cluster 2026_05_30_*) để chạy TRƯỚC migration
 * create_product_variant_table.
 *
 * Idempotent: query INFORMATION_SCHEMA lọc bảng còn MyISAM, ALTER từng cái.
 * Chạy lại lần 2 không có gì để convert → no-op.
 *
 * Tự động loại trừ:
 *  - Bảng system Laravel (migrations, cache, cache_locks, sessions, jobs,
 *    job_batches, failed_jobs, password_reset_tokens, personal_access_tokens)
 *    — đa số đã InnoDB sẵn từ Laravel default.
 *
 * FULLTEXT index preserved tự động qua ALTER ENGINE (MySQL 5.6+ InnoDB
 * support fulltext). Behavior search có khác đôi chút (min_token_size,
 * stopword list) — chấp nhận được vì đang test data.
 *
 * CHÚ Ý production:
 *  - ALTER ENGINE lock toàn bảng + copy dữ liệu — bảng nhiều triệu row có
 *    thể mất giờ. Hiện chạy trên test data, OK.
 *  - Nếu có bảng dùng SPATIAL column (point, polygon...) — MyISAM spatial
 *    khác InnoDB spatial, có thể cần xử lý riêng. Migration sẽ throw nếu
 *    ALTER fail cho từng bảng.
 */
return new class () extends Migration {
    public function up(): void
    {
        $tables = $this->getMyIsamTables();

        if (empty($tables)) {
            return;
        }

        $converted = [];
        $failed = [];

        foreach ($tables as $table) {
            try {
                // Strip mọi MyISAM-specific option cùng lúc convert ENGINE.
                // Các option dưới không tương thích InnoDB → để mặc định:
                //  - ROW_FORMAT=DYNAMIC: InnoDB default modern, tốt cho variable
                //    length columns (VARCHAR, TEXT, BLOB).
                //  - PACK_KEYS=DEFAULT: InnoDB không hỗ trợ key compression
                //    kiểu MyISAM → reset về default.
                //  - DELAY_KEY_WRITE=0: MyISAM-only cache trick.
                //  - CHECKSUM=0: MyISAM table checksum, InnoDB không có.
                // Backtick tên bảng phòng reserved keyword (option, order, ...).
                DB::statement("
                    ALTER TABLE `{$table}`
                    ROW_FORMAT=DYNAMIC,
                    PACK_KEYS=DEFAULT,
                    DELAY_KEY_WRITE=0,
                    CHECKSUM=0,
                    ENGINE=InnoDB
                ");
                $converted[] = $table;
            } catch (\Throwable $e) {
                $failed[$table] = $e->getMessage();
            }
        }

        // Log output qua artisan (hiển thị khi chạy `php artisan migrate -v`)
        if (function_exists('logger')) {
            logger()->info('[InnoDB Convert] Converted: '.implode(', ', $converted));
            if (! empty($failed)) {
                logger()->warning('[InnoDB Convert] Failed: '.json_encode($failed));
            }
        }

        // Nếu có bảng fail, throw để migrate dừng — admin xử lý từng case
        // (thường do bảng có constraint lạ, SPATIAL column, hoặc lock).
        if (! empty($failed)) {
            $msg = "Convert MyISAM → InnoDB fail cho ".count($failed)." bảng:\n";
            foreach ($failed as $table => $error) {
                $msg .= "  - {$table}: {$error}\n";
            }
            throw new \RuntimeException($msg);
        }
    }

    public function down(): void
    {
        // KHÔNG tự động revert về MyISAM. InnoDB là chuẩn hiện đại; revert
        // gây mất FK + transaction support. Migration variant phụ thuộc
        // InnoDB → revert engine sẽ làm hỏng cluster mới.
    }

    /**
     * Lấy danh sách bảng MyISAM trong DB hiện tại, loại trừ system tables
     * Laravel (đã InnoDB sẵn, không cần convert).
     */
    private function getMyIsamTables(): array
    {
        // Bảng system Laravel — đa số đã InnoDB sẵn từ default migration.
        // failed_jobs ĐÃ BỎ khỏi exclude list vì 1 số DB legacy có
        // failed_jobs MyISAM (import từ dump cũ). Convert kèm luôn cho
        // nhất quán — InnoDB cho phép transaction khi log/retry job.
        $systemTables = [
            'migrations',
            'cache',
            'cache_locks',
            'sessions',
            'jobs',
            'job_batches',
            'password_reset_tokens',
            'personal_access_tokens',
        ];

        return DB::table('information_schema.TABLES')
            ->select('TABLE_NAME')
            ->where('TABLE_SCHEMA', DB::raw('DATABASE()'))
            ->where('ENGINE', 'MyISAM')
            ->whereNotIn('TABLE_NAME', $systemTables)
            ->orderBy('TABLE_NAME')
            ->pluck('TABLE_NAME')
            ->all();
    }
};
