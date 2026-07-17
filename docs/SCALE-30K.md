# Checklist đáp ứng 30.000 user active — infun

> Mô hình traffic: ~30k active (đa số browse/search) · ~900 checkout · một phần nhỏ flash-sale tranh chấp variant.
> "Active" ≠ concurrent request — in-flight thực tế vài nghìn (có think-time). Trần thật nằm ở **php-fpm workers + DB write/lock**, không phải số user.

---

## A. ĐÃ LÀM trong repo (giai đoạn tối ưu)

- [x] **Ảnh → R2 + Cloudflare CDN**: disk `image` (s3/R2), `thumbnail()` dùng disk `image`, `resizeImage` **bỏ stat khi disk remote** (build URL thẳng) → trang list không GD/không stat per-request.
- [x] **Pre-gen thumbnail** trên R2: `ProcessImageUpload` job + `images:migrate-r2` command; `config/media.php` (image_disk + thumbnail_sizes: 300×300, 147×147, 1000×1000, 50×50, 800×354). *(Fix 2026-07-15: command bổ sung gom path từ `product_variant.image` + `option_value.image` — trước đó chỉ gom `product`/`product_image`, ảnh swatch variant không được pre-gen → cần chạy lại `images:migrate-r2` một lần, job idempotent nên thumb cũ tự bỏ qua.)*
- [x] **Upload mới tự đẩy R2**: `FileController::upload` dispatch `ProcessImageUpload` (giữ bản local staging).
- [x] **Fix N+1 trang list**: `ProductDTO` về bản đa kho (dùng `sellableStocks`/`canSellQuantity`), khớp eager-load `defaultVariant.productStocks`.
- [x] **Read/Write split (Laravel)**: `config/database.php` block `mysql` có `read`/`write` + `sticky=true`; `.env` có placeholder `DB_WRITE_HOST`/`DB_READ_HOST1`/`DB_READ_HOST2` (đang comment, fallback `DB_HOST`).
- [x] **Redis đã bật**: `SESSION_DRIVER=redis`, `QUEUE_CONNECTION=redis`, `CACHE_STORE=redis`, tách `REDIS_CACHE_CONNECTION=cache`.
- [x] **Chống oversell chắc hơn**: `deductForOrder` cưỡng chế hold (`holderAvailable = on_hand − (reserved − hold_của_mình)`) ở cả guard lẫn vòng trừ → không "nhảy hàng" khi hold hết hạn / bỏ qua bước giữ chỗ / tràn kho. *(Fix 2026-07-15 — chống `on_hand` âm ngoài chủ ý: (1) mọi chỗ nhả/tiêu thụ hold delete theo PK, affected=1 mới được trừ `reserved` → hết double-release khi job dọn hold đua với checkout; (2) `releaseExpired` re-fetch row dưới stock lock + bỏ qua hold khách vừa gia hạn; (3) CMS ghi tồn clamp ≥ 0 + đúng kho mặc định (`ProductVariantWriter`, `updateOnHand` nhận warehouse). `on_hand` âm giờ CHỈ còn 2 đường chủ ý: policy Backorder và tắt `config_stock_checkout`.)*
- [x] **Chống over-redeem coupon/gift/voucher** *(2026-07-15, mẫu 1-3 — mẫu 4-5 claim-wallet/Redis gate để đợt sau)*: `incrementUsedCount` (coupon, gift) và `incrementRedeemed` (voucher) chuyển sang **conditional UPDATE** (guard limit trong 1 câu, trả affected rows — 0 = hết lượt đúng lúc chốt); coupon thêm guard per-user recount dưới X-lock row coupon (`countUsedByUserForUpdate` locking read) → 2 tab cùng user không lách được `uses_customer`; hết lượt → `CouponExhaustedException` rollback cả đơn (catch ở `saveOrder`, cùng vai `InsufficientStockException`). Gift hết suất thì BỎ quà nhưng đơn vẫn đi tiếp; voucher confirm sau thanh toán nên chỉ chặn + log cho CS.
- [x] **k6 scripts**: `k6/product-list.js`, `k6/cart-contention.js`, `k6/mixed-30k.js` (4 nhánh browse/add/checkout/flash).
- [x] Refactor phụ: enum `CouponHistoryStatus`; dời `ZaloPayService`, `CartService→Services\Cart`, `ProductOptionService→Services\Product`, `ZaloPayMacGenerator→Services\Payment`.

---

## B. CẦN TRIỂN KHAI — Hạ tầng

### App tier
- [x] **Nhiều app server stateless** sau LB: staging = `docker-compose.lb.yml` (`--scale infun-php=N`, nginx least_conn + `/__lb_health`); prod dựng LB thật theo `deploy/README.md` bước 3.
- [x] **php-fpm tuning**: staging `docker/php/www.pool.conf` (dynamic, slowlog); prod `deploy/php/www.conf` (static + công thức size `max_children` theo RAM/RSS).
- [x] **OPcache + JIT**: `docker/php/opcache-prod.ini` (staging) / `deploy/php/opcache-prod.ini` (prod) — `validate_timestamps=0` nên deploy PHẢI reload FPM; `config:cache` + `route:cache` trong deploy ritual (`deploy/README.md`).
- [x] `REDIS_CLIENT=predis` → **`phpredis`**: image Docker đã `pecl install redis` + override env; prod theo `deploy/env.production.example`.

### Redis
- [x] **Tách instance/DB**: staging `docker-compose.scale.yml` — `redis` (session+queue, `noeviction` + AOF everysec) vs `redis-cache` (allkeys-lru, no persistence, host :6380); prod `deploy/redis/redis-session.conf` + `redis-cache.conf`. `config/database.php` connection `cache` nhận `REDIS_CACHE_HOST/PORT/PASSWORD` (fallback `REDIS_HOST` → chạy 1 instance như cũ nếu chưa tách).
- [ ] HA: Sentinel hoặc Redis Cluster / managed (ElastiCache) — giai đoạn sau.

### Database (1 master + 2 replica)
- [x] Dựng replication: staging = `docker-compose.scale.yml` (master binlog ROW + GTID domain 1; 2 replica `read_only=1` tự seed + `START SLAVE` qua `docker/mysql/replica-init.sh`); prod = `deploy/mysql/master.cnf` / `replica.cnf` (MariaDB không có `super_read_only` — dùng app user không SUPER) + các bước `deploy/README.md` bước 2.
- [x] Điền IP: staging tự điền qua compose env (`DB_WRITE_HOST=mysql`, `DB_READ_HOST1/2=mysql-replica1/2`); prod điền IP thật theo `deploy/env.production.example` + `config:clear`.
- [x] **ProxySQL** (pool + route + lag-aware): staging = service `proxysql` trong `docker-compose.scale.yml` (app nối 6033=write/6034=read, config `docker/proxysql/proxysql.cnf`, monitor user tự tạo trong `replica-init.sh`); prod = sidecar mỗi app server, `deploy/proxysql/proxysql.cnf` + `deploy/README.md` bước 2b. Replica lag >10s tự bị loại khỏi pool đọc; failover promote replica là ProxySQL tự trỏ write theo `@@read_only`. `config/database.php` thêm `DB_READ_PORT`/`DB_WRITE_PORT`.
- [ ] Monitor **Seconds_Behind_Master** dài hạn (alert) — ProxySQL đã tự SHUN theo lag nhưng vẫn cần dashboard/alert khi lag kéo dài.
- [ ] Rà code không có `->useWritePdo()` / transaction bọc thừa làm mất tác dụng split.
- [ ] Lưu ý: **replica KHÔNG tăng khả năng ghi** — flash-sale vẫn dồn master.

### Search / Images / CDN
- [ ] **Meilisearch** instance riêng, scale độc lập (đã offload search khỏi DB).
- [ ] R2 + **Cloudflare CDN** (custom domain `cdn.lartisan.vn`); cân nhắc **Cloudflare Image Resizing** để khỏi pre-gen từng size.

### Queue / Scheduler
- [x] **Queue worker** chạy bền: staging `infun-queue` (compose, `--max-time=3600` recycle chống leak); prod `deploy/systemd/infun-queue.service` (Restart=always). Horizon = nâng cấp tuỳ chọn (`composer require laravel/horizon`).
- [x] **Scheduler**: staging container `infun-scheduler` (`schedule:work`, CHỈ 1 instance); prod `deploy/systemd/infun-scheduler.service` hoặc cron. **Đã xác nhận `stock:release-expired` schedule `everyMinute()` + `withoutOverlapping()` trong `routes/console.php`** (Laravel 12 — không dùng Kernel.php).

### Middleware / config prod
- [ ] **Gắn lại `cache_page`** (đang tắt để test) — dùng Redis để chia sẻ giữa các app server; cân nhắc invalidate khi sản phẩm đổi.
- [x] Chỉnh **throttle** cho prod: named limiter `throttle:add-to-cart` (30/phút) + `throttle:save-order` (10/phút) — mức đặt trong `config/throttle.php`, nới qua env khi chạy k6 (`THROTTLE_ADD_TO_CART=100000`). Key theo **user → session → IP** thay vì thuần IP (sau LB/CGNAT cả văn phòng chung IP sẽ không chặn nhầm khách thật). Kèm `trustProxies` trong `bootstrap/app.php` để `request->ip()` ra IP client thật sau LB.

---

## C. Flash-sale (điểm nóng GHI) — kế hoạch chi tiết: `docs/FLASH-GATE.md`

- [ ] Nút cổ chai = 1 row `product_stock` bị lock `FOR UPDATE` serialize (đúng, để chống oversell). Muốn chịu tải cao:
  - [ ] **Redis atomic gate** (`DECR stock:{variant}`) làm admission — chỉ người có suất mới vào reservation DB; reconcile Redis↔DB.
  - [ ] hoặc **Waiting room** (Cloudflare Waiting Room / hàng đợi xử lý tuần tự).
- [ ] `innodb_lock_wait_timeout` hợp lý + **retry khi lock timeout** (tránh 5xx).
- [ ] Reservation TTL (`cart_applied_ttl_minutes`) + job dọn hạn phải chạy đủ dày.

---

## D. Kiểm thử tải (k6)

- [ ] 30k thật cần **k6 phân tán**: k6-operator (k8s) hoặc **Grafana Cloud k6** (một máy không kéo nổi 30k VU).
- [ ] Chạy trên **staging giống prod** (DB/Redis/nhiều app server) — không phải local.
- [ ] Kịch bản: `mixed-30k.js` với `-e TARGET=30000` + tỷ lệ nhánh; nới throttle, `payment_code=cod`, `MAIL_MAILER=log`, DB staging (truncate order sau test).
- [ ] **Verify oversell** sau test (SQL): số đơn thành công × qty ≤ tồn ban đầu; `product_stock.reserved ≤ on_hand`; `on_hand ≥ 0`.
- [ ] Quan sát server-side: RAM, CPU, php-fpm busy workers, DB `SHOW PROCESSLIST`/lag, Redis.

---

## E. VIỆC CÒN TREO / cần kiểm ngay

- [ ] `php -l` các file đã sửa (Herd PHP): `StockService`, `ProductDTO`, `MyStorage`, `CheckoutController`, `FileController`, `config/database.php`, `config/media.php`, `k6/*` (JS thì lint riêng).
- [ ] Composer prod: `league/flysystem-async-aws-s3` (R2). `larastan` nếu dùng static analysis.
- [x] Xác nhận `ReleaseExpiredReservationsCommand` được schedule: `routes/console.php` — `stock:release-expired` `everyMinute()` + `withoutOverlapping()` (Laravel 12 không có `app/Console/Kernel.php`).
- [ ] Rà `useWritePdo`/transaction thừa (mục B-DB).
- [ ] (Tùy chọn) test tự động (Pest/PHPUnit) tái hiện đua lock để chống hồi quy oversell.

---

## Thứ tự ưu tiên triển khai

1. ✅ **Redis tách instance** (cache vs session/queue) + phpredis — `docker-compose.scale.yml` + `deploy/redis/`.
2. ✅ **Read/write split** — replica staging tự dựng (GTID); prod theo `deploy/mysql/` + `deploy/README.md`.
3. ✅ **Nhiều app server + LB + ProxySQL** + **php-fpm tuning** — hoàn tất (ProxySQL: `docker/proxysql/` staging + `deploy/proxysql/` prod).
4. ✅ **Queue worker + scheduler** — compose services + systemd units; Horizon = nâng cấp sau.
5. **Gắn lại cache_page (Redis)** + Cloudflare Image Resizing.
6. **Flash-sale gate (Redis/waiting room)** — chỉ khi kịch bản flash thật sự lớn.
7. **k6 phân tán trên staging** để đo & verify oversell.

---

## Đợt triển khai hạ tầng 2026-07-15 (ưu tiên 1-4)

**Staging (Docker, mô phỏng topology prod):**

```bash
docker compose -f docker-compose.yml -f docker-compose.lb.yml -f docker-compose.scale.yml \
  up -d --build --scale infun-php=3
```

File mới: `docker-compose.scale.yml` (2 Redis, master + 2 replica GTID, scheduler),
`docker/mysql/replica-init.sh` (replica tự seed từ master lần đầu),
`docker/php/www.pool.conf`, `docker/php/opcache-prod.ini`.

**Prod:** toàn bộ config + hướng dẫn từng bước trong `deploy/` (README, redis ×2,
mysql master/replica cnf, php-fpm + opcache, systemd queue/scheduler, env example).

**Code:** `config/database.php` — connection `cache` nhận `REDIS_CACHE_HOST/PORT/PASSWORD`
(fallback về `REDIS_HOST`, không tách vẫn chạy như cũ); `.env`/`.env.example` thêm
placeholder. ⚠ Chạy `php -l config/database.php` bằng Herd để xác nhận lint
(sandbox không có PHP — cấu trúc đã soát bằng tay, file nguyên vẹn).

---

## Đợt 2 — ProxySQL + throttle prod (2026-07-15)

**ProxySQL** (staging + prod): app không nối thẳng DB nữa mà qua ProxySQL —
pool connection (chặn connection storm khi php-fpm scale hàng trăm worker),
route theo port (6033→master, 6034→replicas cân tải), tự loại replica lag >10s,
failover theo `@@read_only`. Laravel giữ split+sticky của chính nó. File:
`docker/proxysql/proxysql.cnf`, service trong `docker-compose.scale.yml`,
`deploy/proxysql/proxysql.cnf` (sidecar mỗi app server), `config/database.php`
thêm `DB_READ_PORT`/`DB_WRITE_PORT`. Monitor user tạo tự động trong
`docker/mysql/replica-init.sh` (⚠ volume replica cũ phải tạo tay — xem comment
trong compose).

**Throttle prod:** `routes/web.php` chuyển sang named limiter
(`throttle:add-to-cart` 30/phút, `throttle:save-order` 10/phút — định nghĩa ở
`AppServiceProvider::registerRateLimiters`, mức trong `config/throttle.php`,
nới qua env khi k6). Key user→session→IP + `trustProxies` trong
`bootstrap/app.php` (bắt buộc sau LB, không thì cả site chung 1 quota theo IP
của nginx).

**Checklist verify sau khi up stack:**

```bash
# ProxySQL route đúng chưa (chạy trong container php):
php artisan tinker --execute="dd(DB::selectOne('select @@hostname h')->h, DB::connection()->getReadPdo()->query('select @@hostname')->fetchColumn());"
# → write ra tên container master, read ra replica

# Throttle: spam 31 lần add-to-cart trong 1 phút → lần 31 phải 429
# Pool ProxySQL: mysql -h127.0.0.1 -P6032 -uradmin -pradmin
#   SELECT hostgroup, srv_host, status, ConnUsed, Queries FROM stats_mysql_connection_pool;
```

⚠ Lint tay bằng Herd (sandbox không có PHP):
`php -l` cho `config/database.php`, `config/throttle.php`,
`app/Providers/AppServiceProvider.php`, `bootstrap/app.php`, `routes/web.php`;
và `docker compose -f docker-compose.yml -f docker-compose.lb.yml -f docker-compose.scale.yml config -q`
để Docker tự validate merge.
