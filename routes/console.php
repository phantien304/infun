<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ============================================================================
// Cron schedules
// ----------------------------------------------------------------------------
// reviews:seed — sinh thêm review giả lập để test hiệu năng UI.
//   - Mỗi giờ: thêm 500 review. Sau 24h ≈ 12k review/day.
//   - Tắt qua --no-media/--no-helpful nếu cần.
//   - withoutOverlapping() chống chạy chồng nếu lần trước chưa xong.
//
// Lưu ý: muốn dừng cron, comment block này lại.
// ============================================================================
Schedule::command('reviews:seed', ['500', '--chunk=200'])
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground()
    ->description('Seed 500 fake review mỗi giờ — test hiệu năng list/filter Shopee UX');
