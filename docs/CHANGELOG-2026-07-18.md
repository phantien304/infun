# Changelog — 2026-07-18

Tổng hợp thay đổi trong phiên (voucher reward, dọn config, review enum). Phần
kiểm chứng cần môi trường PHP/DB thật → gom ở mục cuối "Việc chạy trong Claude Code".

---

## 1. VoucherRewardService — tối ưu N+1

`app/Services/Voucher/VoucherRewardService.php`

- `grantForOrder()` nạp toàn bộ grant của đơn **một lần**, `keyBy('rule_id')` rồi
  truyền xuống. Trước đây mỗi rule chạy lại `rewardGrantExists()` và
  `reactivateIfRevoked()` lại nạp full grant → N+K query. Nay còn 1 query.
- `grantOne()` nhận sẵn collection grant; `reactivateIfRevoked()` nhận thẳng grant
  đã khớp (bỏ vòng lặp lọc lại).
- An toàn đồng thời giữ nguyên: chốt vẫn là UNIQUE `uq_vrg_rule_order` + bắt
  duplicate-key, và conditional UPDATE khi reactivate.

## 2. Bug: lệch tên method repo (đã sửa)

`app/Repositories/Eloquent/VoucherRewardRuleRepository.php`

- Interface + service gọi `decrementRewardRuleCount()` nhưng repo lại đặt tên
  `decrementGrantedCount()` → lớp không thoả interface (fatal khi bind) và luồng
  **revoke** gãy. Đã đổi tên method về `decrementRewardRuleCount()`.

## 3. Test cho luồng voucher reward (mới)

`tests/Unit/Services/VoucherRewardServiceTest.php` — PHPUnit + Mockery, mock 3 repo,
không đụng DB. 11 case: đủ/không đủ ngưỡng, phát đôi khi observer bắn 2 lần,
reactivate khi complete lại, rollback khi trạng thái đổi, hết quota, đua
duplicate-key, `max_per_user`, khách vãng lai đếm theo email, revoke khi hủy,
bỏ qua revoke khi voucher đã dùng.

## 4. Seeder rule đầu tiên (mới)

`database/seeders/VoucherRewardRuleSeeder.php` — rule "đơn ≥ 1tr tặng 50k",
dùng `firstOrCreate` (idempotent, không clobber `granted_count`).

## 5. Checklist go-live voucher (mới)

`docs/VOUCHER-REWARD-GOLIVE.md` — các bước migrate / seed / test / kiểm thử thủ công COD.

## 6. Dọn dead config trong `config/module/web/config.php`

Xóa 4 block tàn dư project "Callmeduy", không nơi nào đọc: `seo`, `emotion`,
`safety`, `sort_by`. File từ 127 → 71 dòng. Giữ `url`, `job_mailer`, `product`,
`img_default`, `no_img`, `paginate` (đang dùng).

- Còn nghi vấn (chưa xử lý): orphan partial
  `resources/web/views/category/structure/_sort_by.blade.php` (không include ở đâu,
  gọi hàm `getInfunStudioConfig` không tồn tại), và vài key khả nghi
  (`action_create_or_update`, `storage_domain`, `time_cookie`, `language_default`,
  `page_size`, `relevance_default`, `max_row`, `pagination`, `product.text_backorder`).

## 7. Config review → enum

Tạo mới:

- `app/Enums/ReviewStatus.php` (int: Pending=0, Approved=1, Rejected=2, Hidden=3)
- `app/Enums/ReviewPolicy.php` (string: Public/Login/Purchase, `default()` = Public)

Thay `getCoreConfig('review.status.*' | 'review.policy.*' | 'review.default_policy')`
sang enum ở 9 file: `Review` model, `ReviewObserver`, `ReviewRepository`,
`ReviewService`, `ProductController`, `RebuildReviewAggregateCommand`,
`SeedReviewsCommand`, và 2 blade `product/structure/comment.blade.php` +
`_comment_form.blade.php`.

Xóa block `review` khỏi `config/core/config.php`. **Giữ nguyên** `review_report`
(chưa code nào đọc) và `cache.review.*` (khác block, đang dùng).
Hành vi không đổi: so sánh vẫn trên cùng giá trị int/string; admin override qua
`setting('config_review_policy', ...)` vẫn chạy.

---

## Việc chạy trong Claude Code (cần PHP/DB thật — sandbox không có)

Sandbox của phiên này không có PHP và không nối DB, nên toàn bộ code trên **chưa
được lint/chạy test**. Mở Claude Code tại `E:\xampp82\htdocs\infun` và chạy:

```
# 1) Lint cú pháp các file mới/sửa
php -l app/Services/Voucher/VoucherRewardService.php
php -l app/Repositories/Eloquent/VoucherRewardRuleRepository.php
php -l app/Enums/ReviewStatus.php
php -l app/Enums/ReviewPolicy.php
php -l tests/Unit/Services/VoucherRewardServiceTest.php
php -l database/seeders/VoucherRewardRuleSeeder.php
php -l config/module/web/config.php
php -l config/core/config.php

# 2) Xóa cache vì đã sửa config + đổi tên method repo
php artisan config:clear
php artisan repository:clear

# 3) Chạy test
php artisan test --filter=VoucherRewardServiceTest

# 4) Voucher reward: migrate + seed rule đầu tiên
php artisan migrate
php artisan db:seed --class=Database\\Seeders\\VoucherRewardRuleSeeder
```

Kiểm tra thêm:

- Test review sau khi đổi enum: `php artisan test --filter=Review` (nếu có),
  và mở trang sản phẩm xem form review + policy gate chạy đúng.
- `ReviewPolicy::Public` dùng từ khóa `public` làm tên case — PHP 8.1+ cho phép,
  nhưng `php -l` sẽ xác nhận chắc chắn.
- Kiểm thử thủ công COD theo `docs/VOUCHER-REWARD-GOLIVE.md` mục 6.

> Nguyên tắc phân công đã dùng: nghiên cứu / sửa code / viết doc làm ở Cowork;
> chạy `php -l`, test, migrate, seed, commit làm ở Claude Code (chạy trên toolchain
> thật). Code do Cowork tạo coi như "chưa kiểm chứng" tới khi Claude Code chạy xanh.

---

# Phiên 2 (cùng ngày) — Hạ tầng 30k: mục 5-7 SCALE-30K

## 5. cache_page bật lại + invalidate + CF Image Resizing

- `routes/web.php`: gắn lại `cache_page` vào group `['maintenance', 'cache_page',
  'limit_access']` (các route động đã có `withoutMiddleware` từ trước).
- `app/Helpers/CacheGate.php`: thêm `PAGE_TAG`, `pageStore()` (tag kép
  `[GLOBAL_TAG, PAGE_TAG]`), `flushPages()` (chỉ xoá HTML cache, giữ repo cache).
- `app/Http/Middleware/CachePage.php`: dùng `pageStore()`.
- `app/Observers/CacheFlushObserver.php`: `flush()` gọi thêm `flushPages()` —
  mọi content model trong `$cacheMap` đổi là page cache sạch.
- `config/media.php` + `app/Helpers/MyStorage.php`: khối `cf_resizing` +
  `cloudflareResizeUrl()` — URL `/cdn-cgi/image/...`, opt-in `CF_IMAGE_RESIZING`.

## 6. Flash-sale gate (FLASH-GATE Phase 1-3)

- MỚI: `config/flash_gate.php`, `app/Services/Stock/FlashGateService.php` (Lua
  acquire/release/clamp-down, fail-open, index SET, chạy cả phpredis/predis),
  4 command `FlashGate{Seed,Status,Teardown,Reconcile}Command`,
  `app/Helpers/ConcurrencyRetry.php` (retry 1205/1213 jitter 50-150ms).
- `StockService`: inject `FlashGateService`; `flashGatePrePass()` (delta-debit
  trước transaction, hoàn khi fail); credit `DB::afterCommit` trong
  `releaseReservationRow`; transaction bọc `ConcurrencyRetry`; QueryException
  kẹt lock → `['busy' => true]`.
- `CreateOrderService::create`: `ConcurrencyRetry` thay `attempts: 3`.
- `CheckoutController`: busy → `messages.ErrorSystemBusy` (key mới trong
  `resources/lang/vi/messages.php`) ở cả index lẫn saveOrder.
- Repo: `sellableProductStocks()` (không lock) thêm vào interface + Eloquent.
- `routes/console.php`: schedule `flash-gate:reconcile` everyMinute.
- `docker-compose.scale.yml`: master thêm `--innodb-lock-wait-timeout=10`.
- `deploy/env.production.example`: khối CF Image Resizing + Flash gate.

## 7. k6 phân tán + verify

- MỚI: `k6/DISTRIBUTED.md` (3 phương án phân tán, chuẩn bị staging, ma trận
  gate OFF/ON, quan sát, ngưỡng đạt) + `k6/verify-oversell.sql` (4 bất biến DB
  + công thức gate-leak). Chạy thật trên staging = việc vận hành, chưa chạy.

## Việc chạy trong Claude Code (phiên 2)

```bash
# 1) Lint toàn bộ file đụng tới
php -l app/Services/Stock/FlashGateService.php
php -l app/Services/Stock/StockService.php
php -l app/Helpers/ConcurrencyRetry.php app/Helpers/CacheGate.php app/Helpers/MyStorage.php
php -l app/Http/Middleware/CachePage.php app/Observers/CacheFlushObserver.php
php -l app/Http/Controllers/Web/CheckoutController.php app/Services/Checkout/CreateOrderService.php
for f in app/Console/Commands/FlashGate*.php; do php -l "$f"; done
php -l config/flash_gate.php config/media.php routes/web.php routes/console.php
php -l app/Repositories/Interfaces/ProductStockRepositoryInterface.php
php -l app/Repositories/Eloquent/ProductStockRepository.php
php -l resources/lang/vi/messages.php

# 2) Bind + schedule + route OK?
php artisan config:clear && php artisan route:list > /dev/null
php artisan schedule:list | grep flash-gate

# 3) Smoke gate (cần Redis chạy):
#    FLASH_GATE_ENABLED=true trong .env → config:clear
php artisan flash-gate:seed <variant_id>
php artisan flash-gate:status
php artisan flash-gate:teardown --all

# 4) Smoke cache_page: mở 2 lần 1 trang guest → response header X-Cache MISS→HIT;
#    sửa 1 sản phẩm trong CMS → mở lại phải MISS (observer flush).
```

> Lưu ý: `docker compose -f docker-compose.yml -f docker-compose.lb.yml \
> -f docker-compose.scale.yml config -q` để validate merge sau khi thêm flag
> innodb-lock-wait-timeout.
