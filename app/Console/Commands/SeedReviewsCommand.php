<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Seed dữ liệu review giả lập cho test hiệu năng UI Shopee UX:
 *  - review root + review_rating (multi-criteria per review)
 *  - review_tag_pivot (tỉ lệ ~60% review có 1-3 tag)
 *  - review_media (tỉ lệ ~25% review có 1-3 ảnh)
 *  - review_helpful (tỉ lệ ~50% review có 1-50 helpful vote từ user khác)
 *
 * Performance — đi raw DB::table()->insert() theo chunk, BYPASS observer +
 * Eloquent events. Aggregate (product.review_count, rating_avg, rating_sum,
 * rating_distribution) được rebuild bằng 1 UPDATE batch cuối flow thay vì
 * fire observer N lần.
 *
 * MySQL placeholder limit (~65535/statement): mỗi bảng chunk theo placeholder
 * count thực — review (~25 cột) chunk ≤ 2000 row/batch, review_rating chunk
 * 5000+ row OK do chỉ 5 cột.
 *
 * Cách dùng:
 *   php artisan reviews:seed                              # mặc định 100000 review
 *   php artisan reviews:seed 50000
 *   php artisan reviews:seed 100000 --chunk=2000
 *   php artisan reviews:seed 100000 --truncate            # clear review + cluster trước
 *   php artisan reviews:seed 100000 --product-id=49       # chỉ seed cho 1 product
 *   php artisan reviews:seed 100000 --no-media            # tắt ảnh
 *   php artisan reviews:seed 100000 --no-helpful          # tắt vote
 *
 * Sau khi seed: chạy `ANALYZE TABLE review, review_rating, review_helpful;`
 * + `php artisan cache:clear`.
 */
class SeedReviewsCommand extends Command
{
    protected $signature = 'reviews:seed
        {count=100000 : Số review cần tạo}
        {--chunk=500 : Số row mỗi batch INSERT review (giảm xuống chống OOM khi helpful vote nhiều)}
        {--truncate : Truncate toàn cluster review (review/_rating/_media/_helpful/_tag_pivot) trước}
        {--product-id= : Chỉ seed cho 1 product_id duy nhất (nếu rỗng: random từ toàn bộ product)}
        {--no-media : Không sinh review_media}
        {--no-helpful : Không sinh review_helpful}
        {--no-tag : Không sinh review_tag_pivot}
        {--no-aggregate : Skip UPDATE product.rating_avg cuối flow. Dùng khi chạy split-run nhiều process — gọi `reviews:rebuild-aggregate` 1 lần sau khi tất cả seed xong}';

    protected $description = 'Seed N review giả lập (bulk insert) — multi-criteria + media + tags + helpful vote';

    // ------------------------------- Pools --------------------------------

    private const FIRST_NAMES = [
        'Nguyễn','Trần','Lê','Phạm','Hoàng','Huỳnh','Phan','Vũ','Võ','Đặng',
        'Bùi','Đỗ','Hồ','Ngô','Dương','Lý','Đinh','Trịnh','Cao','Tô',
    ];
    private const MID_NAMES = [
        'Văn','Thị','Đức','Hữu','Quốc','Minh','Thành','Hồng','Bích','Thu',
        'Hải','Thái','Anh','Ngọc','Phương','Tuấn','Quang','Khánh','Mai','Hà',
    ];
    private const LAST_NAMES = [
        'An','Bình','Châu','Dũng','Duy','Giang','Hà','Hải','Hằng','Hạnh',
        'Hiếu','Hoa','Hùng','Hương','Khoa','Khôi','Lan','Linh','Long','Mai',
        'Minh','My','Nam','Nga','Nhi','Phong','Phúc','Quân','Quỳnh','Sơn',
        'Thắng','Thảo','Thư','Tiến','Toàn','Trang','Trí','Trinh','Tú','Vy',
    ];

    private const POSITIVE_OPENS = [
        'Sản phẩm tuyệt vời', 'Mình rất ưng', 'Quá đỉnh', 'Đáng đồng tiền',
        'Mua xong không hối hận', 'Y như mô tả', 'Hơn cả mong đợi',
        'Chất lượng tốt', 'Đẹp lung linh', 'Đúng gu mình',
    ];
    private const POSITIVE_BODIES = [
        'shop giao hàng nhanh, đóng gói cẩn thận, không hư hỏng gì.',
        'đóng gói chắc chắn, nhân viên tư vấn nhiệt tình.',
        'chất liệu xịn, dùng êm tay, giá hợp lý.',
        'giao đúng hẹn, bao bì đẹp, sản phẩm zin nguyên seal.',
        'mở hộp ra thấy ưng mắt liền, đáng tiền lắm.',
        'shop chu đáo, có tặng kèm quà nhỏ, rất hài lòng.',
        'sản phẩm chính hãng, dùng mượt từ ngày đầu.',
        'đúng size, đúng màu, không khác ảnh đăng.',
    ];
    private const NEUTRAL_OPENS = [
        'Tạm ổn', 'Bình thường', 'Cũng được', 'Không quá xuất sắc',
        'Chấp nhận được', 'Ổn áp với giá tiền',
    ];
    private const NEUTRAL_BODIES = [
        'giao hơi chậm chút, nhưng sản phẩm ok.',
        'hộp móp nhẹ, sản phẩm bên trong ổn.',
        'màu hơi khác ảnh một chút, vẫn dùng được.',
        'không tệ, nhưng cũng không có gì đặc biệt.',
    ];
    private const NEGATIVE_OPENS = [
        'Không như kỳ vọng', 'Hơi thất vọng', 'Cần cải thiện',
        'Sản phẩm chưa ổn',
    ];
    private const NEGATIVE_BODIES = [
        'giao chậm 3 ngày so với hẹn, đóng gói sơ sài.',
        'sản phẩm khác ảnh nhiều, màu sai.',
        'có vết xước nhẹ khi nhận hàng.',
        'phản hồi shop chậm, kém nhiệt tình.',
    ];

    private const TITLES = [
        '', '', '', // 30% không có title
        'Rất hài lòng', 'Đáng tiền', 'Recomend!', 'Sẽ mua lại',
        'Chất lượng ok', 'Giao hàng nhanh', 'Sản phẩm đẹp',
    ];

    private const IMAGES = [
        'seed/review/r1.jpg','seed/review/r2.jpg','seed/review/r3.jpg',
        'seed/review/r4.jpg','seed/review/r5.jpg','seed/review/r6.jpg',
        'seed/review/r7.jpg','seed/review/r8.jpg','seed/review/r9.jpg',
        'seed/review/r10.jpg',
    ];

    public function handle(): int
    {
        $count       = max(1, (int) $this->argument('count'));
        $chunk       = max(100, (int) $this->option('chunk'));
        $singlePid   = $this->option('product-id') ? (int) $this->option('product-id') : null;
        $withMedia   = ! $this->option('no-media');
        $withHelpful = ! $this->option('no-helpful');
        $withTag     = ! $this->option('no-tag');

        $this->info("Bắt đầu seed {$count} review (chunk={$chunk})…");

        // --- Lookup tables (1 query lần) ---
        $productIds = $singlePid
            ? [$singlePid]
            : DB::table('product')->where('is_review', 1)->pluck('id')->all();
        if (empty($productIds)) {
            $this->error('Không có product nào để seed review. Chạy products:seed trước.');
            return self::FAILURE;
        }

        $criteriaIds = DB::table('review_criteria')->where('is_active', 1)->pluck('id')->all();
        if (empty($criteriaIds)) {
            $this->warn('Chưa có review_criteria — sẽ KHÔNG seed multi-criteria pivot.');
        }
        $tagIds = $withTag
            ? DB::table('review_tag')->where('is_active', 1)->pluck('id')->all()
            : [];

        // Pool user_id thực để hash voted bằng IP — fallback 0 cho guest review.
        $userIds = DB::table('user')->limit(5000)->pluck('id')->all();
        if (empty($userIds)) {
            $userIds = [0];
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        try {
            if ($this->option('truncate')) {
                $this->warn('Truncating cluster review…');
                foreach (['review_helpful','review_tag_pivot','review_media','review_rating','review','review_report'] as $tbl) {
                    if (Schema::hasTable($tbl)) {
                        DB::table($tbl)->truncate();
                    }
                }
            }

            $startReviewId = (int) DB::table('review')->max('id');  // chỉ để log
            $bar = $this->output->createProgressBar($count);
            $bar->start();

            // Reconnect định kỳ — PDO buffer + prepared statement metadata tích tụ
            // sau N insert lớn, không gc_collect_cycles() giải được. Cứ 50 batch
            // (mỗi batch ~7k child row) reset connection, RAM PHP về baseline ~80MB.
            $batchesSinceReconnect = 0;
            $reconnectEvery = 50;

            for ($offset = 0; $offset < $count; $offset += $chunk) {
                $batch = min($chunk, $count - $offset);

                // Build review rows TRƯỚC (không gán id) — AUTO_INCREMENT cấp id,
                // tránh va legacy data (migration backfill đã insert review_rating
                // cho review.id legacy → explicit id collision). Sau insert đọc
                // LAST_INSERT_ID = id đầu batch, suy ra dải [first, first+batch-1].
                $reviewRows = [];
                $perRowMeta = [];   // metadata để dựng child rows sau khi biết id

                for ($i = 0; $i < $batch; $i++) {
                    $productId    = $productIds[array_rand($productIds)];
                    $userId       = $userIds[array_rand($userIds)];
                    $rating       = $this->randomRating();
                    $author       = $this->randomName();
                    [$title, $text] = $this->randomTextByRating($rating);
                    $createdAt    = Carbon::now()->subDays(random_int(0, 540))
                        ->subMinutes(random_int(0, 1440));

                    // Quyết định trước: review có media? có tag? bao nhiêu vote?
                    // Tính ngay helpful_count + media_count để cập nhật cột denormalize
                    // trên review row (counter denormalize không cần observer).
                    $hasMedia    = $withMedia   && random_int(1, 4) === 1;
                    $mediaCount  = $hasMedia ? random_int(1, 3) : 0;
                    $hasTagsRow  = $withTag    && ! empty($tagIds) && random_int(1, 10) <= 6;
                    // Max 15 vote/review (trước là 50 → OOM với chunk lớn). Vẫn realistic.
                    $hasVote     = $withHelpful && random_int(1, 2) === 1;
                    $voteN       = $hasVote ? random_int(1, 15) : 0;
                    // Phân phối vote_type: 80% helpful, 20% unhelpful.
                    $helpfulN    = 0;
                    $unhelpN     = 0;
                    if ($voteN) {
                        for ($v = 0; $v < $voteN; $v++) {
                            if (random_int(1, 10) <= 8) {
                                $helpfulN++;
                            } else {
                                $unhelpN++;
                            }
                        }
                    }

                    $reviewRows[] = [
                        'product_id'         => $productId,
                        'product_variant_id' => null,
                        'order_id'           => null,
                        'user_id'            => $userId,
                        'ip'                 => $this->randomIp(),
                        'author'             => $author,
                        // email tạm null — patch sau insert nếu cần (KHÔNG dùng id
                        // trong email vì chưa biết id).
                        'email'              => null,
                        'title'              => $title ?: null,
                        'text'               => $text,
                        'rating'             => $rating,
                        'status'             => getCoreConfig('review.status.approved'),
                        'is_publish'         => 1,
                        'is_anonymous'       => random_int(1, 10) === 1 ? 1 : 0,
                        'language_code'      => 'vi',
                        'helpful_count'      => $helpfulN,
                        'unhelpful_count'    => $unhelpN,
                        'reply_count'        => 0,
                        'media_count'        => $mediaCount,
                        'edit_count'         => 0,
                        'last_edited_at'     => null,
                        'approved_at'        => $createdAt,
                        'approved_by'        => null,
                        'source'             => 'seed',
                        'user_agent'         => 'SeedReviewsCommand',
                        'created_at'         => $createdAt,
                        'updated_at'         => $createdAt,
                        'deleted_at'         => null,
                    ];

                    // Lưu metadata để dựng child rows sau khi biết id thật.
                    $perRowMeta[] = [
                        'rating'      => $rating,
                        'createdAt'   => $createdAt,
                        'mediaCount'  => $mediaCount,
                        'hasTags'    => $hasTagsRow,
                        'voteN'       => $voteN,
                    ];
                }

                // Insert review (AUTO_INCREMENT cấp id) — đọc id đầu batch qua
                // LAST_INSERT_ID. MySQL guarantee: với multi-row INSERT,
                // LAST_INSERT_ID() trả id đầu, id còn lại tuần tự (yêu cầu
                // innodb_autoinc_lock_mode != 2 hoặc batch INSERT đơn → mặc định
                // OK với InnoDB).
                DB::table('review')->insert($reviewRows);
                $firstId = (int) DB::getPdo()->lastInsertId();

                // Build child rows với id thật.
                $ratings = [];
                $media = [];
                $tagsPiv = [];
                $helpfuls = [];
                foreach ($perRowMeta as $i => $meta) {
                    $rid       = $firstId + $i;
                    $rating    = $meta['rating'];
                    $createdAt = $meta['createdAt'];

                    foreach ($criteriaIds as $cid) {
                        $r = max(1, min(5, $rating + random_int(-1, 1)));
                        $ratings[] = [
                            'review_id'          => $rid,
                            'review_criteria_id' => $cid,
                            'rating'             => $r,
                            'created_at'         => $createdAt,
                            'updated_at'         => $createdAt,
                        ];
                    }

                    for ($k = 0; $k < $meta['mediaCount']; $k++) {
                        $media[] = [
                            'review_id'  => $rid,
                            'type'       => 'image',
                            'url'        => self::IMAGES[array_rand(self::IMAGES)],
                            'thumbnail'  => null,
                            'mime'       => 'image/jpeg',
                            'file_size'  => random_int(50_000, 800_000),
                            'width'      => 1080,
                            'height'     => 1080,
                            'duration'   => null,
                            'sort_order' => $k,
                            'is_active'  => 1,
                            'created_at' => $createdAt,
                            'updated_at' => $createdAt,
                            'deleted_at' => null,
                        ];
                    }

                    if ($meta['hasTags']) {
                        $picks = (array) array_rand(array_flip($tagIds), min(random_int(1, 3), count($tagIds)));
                        foreach ($picks as $tagId) {
                            $tagsPiv[] = [
                                'review_id'     => $rid,
                                'review_tag_id' => (int) $tagId,
                                'created_at'    => $createdAt,
                            ];
                        }
                    }

                    for ($v = 0; $v < $meta['voteN']; $v++) {
                        $voteType = random_int(1, 10) <= 8 ? 1 : -1;
                        $voterUid = $userIds[array_rand($userIds)];
                        $helpfuls[] = [
                            'review_id'  => $rid,
                            'user_id'    => $voterUid,
                            'vote_type'  => $voteType,
                            'ip'         => $this->randomIp(),
                            'created_at' => $createdAt,
                            'updated_at' => $createdAt,
                        ];
                    }
                }

                // insertOrIgnore everywhere → idempotent + defensive với legacy data.
                // Unset NGAY sau insert để giảm peak memory — PHP array overhead lớn
                // (mỗi row ~1-2KB do hash table), helpfuls có thể 30k+ row/batch.
                if (! empty($ratings)) {
                    $this->bulkInsert('review_rating', $ratings, 5000, true);
                    unset($ratings);
                }
                if (! empty($media)) {
                    $this->bulkInsert('review_media', $media, 2000);
                    unset($media);
                }
                if (! empty($tagsPiv)) {
                    DB::table('review_tag_pivot')->insertOrIgnore($tagsPiv);
                    unset($tagsPiv);
                }
                if (! empty($helpfuls)) {
                    $this->bulkInsert('review_helpful', $helpfuls, 3000, true);
                    unset($helpfuls);
                }
                unset($reviewRows, $perRowMeta);

                // Buộc GC dọn dẹp cycles giữa các batch — chống OOM ở dataset 100k+.
                gc_collect_cycles();

                // Reconnect định kỳ để giải phóng PDO statement cache (xem comment
                // ở khai báo $reconnectEvery). SET FOREIGN_KEY_CHECKS là session-level
                // → connection mới phải re-apply.
                if (++$batchesSinceReconnect >= $reconnectEvery) {
                    DB::disconnect();
                    DB::statement('SET FOREIGN_KEY_CHECKS=0');
                    $batchesSinceReconnect = 0;
                }

                $bar->advance($batch);
            }
            $bar->finish();
            $this->newLine();

            // Cập nhật usage_count cho tag — count actual rows trong pivot.
            if ($withTag && ! empty($tagIds)) {
                $this->info('Cập nhật usage_count cho review_tag…');
                DB::statement('
                    UPDATE review_tag rt
                    LEFT JOIN (
                        SELECT review_tag_id, COUNT(*) AS cnt
                        FROM review_tag_pivot
                        GROUP BY review_tag_id
                    ) p ON p.review_tag_id = rt.id
                    SET rt.usage_count = COALESCE(p.cnt, 0)
                ');
            }

            // Rebuild aggregate cache trên product — chỉ run 1 lần cho mọi product
            // chạm bởi seed (tận dụng SQL group by, không loop observer N lần).
            // Skip khi chạy split-run; cuối cùng gọi reviews:rebuild-aggregate.
            if ($this->option('no-aggregate')) {
                $this->warn('Skip aggregate UPDATE (--no-aggregate). Nhớ chạy:');
                $this->warn('  php artisan reviews:rebuild-aggregate');
                $maxId = (int) DB::table('review')->max('id');
                $this->info("Done — seeded {$count} review (max id = {$maxId}).");
                return self::SUCCESS;
            }
            $this->info('Rebuild aggregate (review_count, rating_avg, rating_sum, rating_distribution) trên product…');
            DB::statement('
                UPDATE product p
                LEFT JOIN (
                    SELECT product_id,
                           COUNT(*)        AS cnt,
                           SUM(rating)     AS rsum,
                           AVG(rating)     AS ravg,
                           SUM(rating = 1) AS s1,
                           SUM(rating = 2) AS s2,
                           SUM(rating = 3) AS s3,
                           SUM(rating = 4) AS s4,
                           SUM(rating = 5) AS s5
                    FROM review
                    WHERE status = ?
                      AND deleted_at IS NULL
                    GROUP BY product_id
                ) r ON r.product_id = p.id
                SET p.review_count        = COALESCE(r.cnt, 0),
                    p.rating_sum          = COALESCE(r.rsum, 0),
                    p.rating_avg          = COALESCE(r.ravg, 0),
                    p.rating_distribution = CASE
                        WHEN r.cnt IS NULL THEN NULL
                        ELSE JSON_OBJECT(
                            "1", COALESCE(r.s1, 0),
                            "2", COALESCE(r.s2, 0),
                            "3", COALESCE(r.s3, 0),
                            "4", COALESCE(r.s4, 0),
                            "5", COALESCE(r.s5, 0)
                        )
                    END,
                    p.rating_updated_at = NOW()
            ', [(int) getCoreConfig('review.status.approved')]);
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }

        $maxId = (int) DB::table('review')->max('id');
        $this->info(sprintf(
            'Done — seeded %d review (range %d → %d). Nhớ: cache:clear + ANALYZE TABLE review.',
            $count,
            $startReviewId + 1,
            $maxId
        ));
        return self::SUCCESS;
    }

    /* =========================== Helpers =========================== */

    /** Phân phối realistic: 5★ 55%, 4★ 25%, 3★ 12%, 2★ 5%, 1★ 3%. */
    private function randomRating(): int
    {
        $r = random_int(1, 100);
        if ($r <= 55) {
            return 5;
        }
        if ($r <= 80) {
            return 4;
        }
        if ($r <= 92) {
            return 3;
        }
        if ($r <= 97) {
            return 2;
        }
        return 1;
    }

    private function randomName(): string
    {
        return self::FIRST_NAMES[array_rand(self::FIRST_NAMES)] . ' '
            . self::MID_NAMES[array_rand(self::MID_NAMES)] . ' '
            . self::LAST_NAMES[array_rand(self::LAST_NAMES)];
    }

    private function randomIp(): string
    {
        return random_int(1, 255) . '.' . random_int(0, 255) . '.'
             . random_int(0, 255) . '.' . random_int(1, 254);
    }

    /** Trả [title, text] dựa rating. */
    private function randomTextByRating(int $rating): array
    {
        if ($rating >= 4) {
            $open = self::POSITIVE_OPENS[array_rand(self::POSITIVE_OPENS)];
            $body = self::POSITIVE_BODIES[array_rand(self::POSITIVE_BODIES)];
        } elseif ($rating == 3) {
            $open = self::NEUTRAL_OPENS[array_rand(self::NEUTRAL_OPENS)];
            $body = self::NEUTRAL_BODIES[array_rand(self::NEUTRAL_BODIES)];
        } else {
            $open = self::NEGATIVE_OPENS[array_rand(self::NEGATIVE_OPENS)];
            $body = self::NEGATIVE_BODIES[array_rand(self::NEGATIVE_BODIES)];
        }

        $title = self::TITLES[array_rand(self::TITLES)];
        $text  = $open . ', ' . $body;

        // Đôi khi nối thêm 1 câu thứ 2 cho dài.
        if (random_int(1, 3) === 1) {
            $extra = $rating >= 4
                ? self::POSITIVE_BODIES[array_rand(self::POSITIVE_BODIES)]
                : self::NEUTRAL_BODIES[array_rand(self::NEUTRAL_BODIES)];
            $text .= ' ' . Str::ucfirst($extra);
        }

        return [$title, $text];
    }

    /**
     * Bulk insert với array_chunk để né MySQL placeholder limit (65535/stmt).
     * $useIgnore = true cho review_helpful (UNIQUE review_id+user_id có thể duplicate).
     */
    private function bulkInsert(string $table, array $rows, int $perBatch, bool $useIgnore = false): void
    {
        foreach (array_chunk($rows, $perBatch) as $part) {
            if ($useIgnore) {
                DB::table($table)->insertOrIgnore($part);
            } else {
                DB::table($table)->insert($part);
            }
        }
    }
}
