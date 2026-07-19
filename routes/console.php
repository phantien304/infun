<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('reviews:seed', ['500', '--chunk=200'])
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground()
    ->description('Seed 500 fake review mỗi giờ — test hiệu năng list/filter Shopee UX');

Schedule::command('stock:release-expired')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground()
    ->description('Nhả hold tồn kho hết hạn (chống giữ tồn ảo khi flash sale)');

// Thoát nhanh khi index gate rỗng — chạy mỗi phút không tốn kém.
// Chỉ clamp XUỐNG (gate = min(gate, sellable DB)); nhập thêm hàng giữa sale
// thì chạy lại flash-gate:seed. Xem docs/FLASH-GATE.md Phase 2.
Schedule::command('flash-gate:reconcile')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground()
    ->description('Cân quota Redis flash-gate theo sellable DB (chống leak suất)');

Schedule::command('affiliate:prune-clicks')
    ->dailyAt('02:10')
    ->withoutOverlapping()
    ->runInBackground()
    ->description('Prune affiliate_click cũ hơn retention (mặc định 90 ngày)');

Schedule::command('affiliate:health-check')
    ->dailyAt('08:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->description('Cảnh báo affiliate CR bất thường / click spam (chỉ log, không tự khóa)');
