# Kế hoạch mục 6 — Flash-sale gate (Redis admission) — infun

> Vấn đề: mọi `reserveOne()` mở transaction rồi `lockSellableProductStocks()` (`FOR UPDATE`)
> trên row `product_stock` của variant. Flash-sale N nghìn người cùng 1 variant → N request
> xếp hàng giữ connection + php-fpm worker chờ lock → timeout/5xx dây chuyền.
>
> Giải pháp: **Redis atomic gate làm cửa admission TRƯỚC khi mở transaction DB**.
> Chỉ người "có suất" (gate còn quota) mới được vào đường reservation DB; người hết suất
> bị từ chối ngay bằng 1 lệnh Redis (~µs), không đụng MySQL. DB lock vẫn giữ nguyên
> làm tầng chống oversell cuối (gate chỉ giảm tranh chấp, không thay thế lock).

**Nguyên tắc an toàn:**
- Gate **fail-open**: Redis lỗi/không có key → đi thẳng đường DB như hiện tại (chậm nhưng đúng).
- Gate key đặt trên Redis **session/queue** (`noeviction` + AOF) — TUYỆT ĐỐI không đặt trên
  instance `cache` (`allkeys-lru` sẽ evict key giữa sale).
- Gate chỉ bật cho variant được seed (opt-in per-variant) — checkout thường không thêm phụ thuộc Redis.

---

## Phase 1 — Core gate (code chính) ✅ 2026-07-18

- [x] **`config/flash_gate.php`**: `enabled` (env `FLASH_GATE_ENABLED`), `redis_connection`
      (default — KHÔNG phải `cache`), prefix key `gate:stock:{variant_id}`, log channel.
- [x] **`app/Services/Stock/FlashGateService.php`**:
  - `tryAcquire(int $variantId, int $qty): ?bool` — Lua atomic
    `if GET >= qty then DECRBY, return 1 else return 0`; key không tồn tại → `null` (variant
    không gated, đi đường thường). Lua để tránh race DECR-âm-rồi-INCR-trả.
  - `release(int $variantId, int $qty)` — `INCRBY` trả suất (chỉ khi key còn tồn tại,
    cũng Lua để không hồi sinh key đã teardown).
  - `isGated`, `remaining` (đọc cho command/monitor).
  - Mọi lỗi Redis: catch → log warning → coi như not-gated (fail-open).
- [x] **Hook vào `StockService::reserveCheckout`** — pre-pass TRƯỚC `transaction()` (`flashGatePrePass`, có check `isGated` GET rẻ trước khi query hold DB):
  1. Với mỗi item gated: tính **delta** so với hold hiện có (`reservationsForVariant`) —
     tăng qty mới debit phần tăng, giảm qty thì credit; tránh double-debit khi user bấm lại.
  2. `tryAcquire` từng item; fail → `release` lại các item đã debit trong pre-pass, trả
     `['ok'=>false, 'failed'=>…]` y như `ReservationUnavailable` (UI hiện tại dùng lại được).
  3. Pre-pass OK → vào transaction DB như cũ; nếu DB reservation vẫn fail
     (`ReservationUnavailable`) → credit lại toàn bộ delta đã debit.
- [x] **Credit khi nhả hold** — `releaseReservationRow()` (dùng chung cho `releaseHolder`,
      `releaseExpired`, hold hết hạn): sau khi delete hold thành công, credit qua
      **`DB::afterCommit`** (rollback không credit nhầm). `deductForOrder` (hold → bán thật)
      KHÔNG credit — suất đã tiêu thụ đúng.

## Phase 2 — Vận hành gate (seed / reconcile / teardown) ✅ 2026-07-18

- [x] **`artisan flash-gate:seed {variantId...}`**: trong transaction + lock stock row,
      tính `sellable = Σ max(0, on_hand − reserved)` các kho sellable → `SET gate:stock:{v}`.
      In ra giá trị seed. (Chạy ngay trước giờ sale.) Kèm index SET `gate:stock:index`
      để status/reconcile không phải SCAN. Chặn seed khi `FLASH_GATE_ENABLED` tắt.
- [x] **`artisan flash-gate:status`** / **`flash-gate:teardown {variantId...|--all}`**
      (teardown = DEL key → variant về đường DB thường). Status in bảng gate vs DB + drift.
- [x] **`artisan flash-gate:reconcile`** + schedule `everyMinute()->withoutOverlapping()`
      trong `routes/console.php`, chỉ chạy khi có key gate: với mỗi variant gated, tính lại
      `db_sellable` (KHÔNG lock, đọc replica được) và **clamp xuống**:
      `gate = min(gate, db_sellable)` bằng Lua. Chỉ clamp xuống — không tự nâng lên
      (nâng do CMS nhập thêm hàng thì chạy lại `seed`). Log drift > ngưỡng.
- [x] Case CMS đổi tồn giữa sale (`updateOnHand`): ghi chú vận hành — sau khi sửa tồn
      variant đang gated phải chạy `flash-gate:seed` lại (reconcile chỉ tự xử lý chiều giảm).
      Đã ghi trong docblock seed command + comment schedule + env example.

## Phase 3 — Chống 5xx khi lock vẫn dồn (mục C còn lại) ✅ 2026-07-18

- [x] `innodb_lock_wait_timeout = 10`: prod `deploy/mysql/master.cnf` (đã có sẵn);
      staging thêm `--innodb-lock-wait-timeout=10` vào command master trong
      `docker-compose.scale.yml`.
- [x] **Retry khi lock timeout** (1205, deadlock 1213): `App\Helpers\ConcurrencyRetry::run`
      — tối đa 2 retry, jitter 50–150ms — bọc transaction `reserveCheckout` và
      `CreateOrderService::create` (thay `attempts:3` không-jitter; `deductForOrder` nằm
      trong transaction này). Hết retry → `messages.ErrorSystemBusy` ("hệ thống đang có
      rất nhiều người đặt hàng...") ở cả trang checkout lẫn saveOrder — không 5xx.
- [x] Rà TTL hold: `stock:release-expired` đã `everyMinute()` + có `--limit` option;
      ghi chú vận hành sale lớn (TTL ngắn hơn + limit cao hơn) trong SCALE-30K mục C.

## Phase 4 — Kiểm chứng (◐ chuẩn bị xong, chờ chạy trên staging)

- [ ] Unit test (Pest): Lua debit/credit đúng floor 0; delta khi đổi qty; credit khi hold
      expire; fail-open khi Redis chết; reconcile chỉ clamp xuống. *(Cần môi trường
      PHP + Redis thật — sandbox không có; xem SCALE-30K "Còn treo sau đợt 3".)*
- [x] Kịch bản + lệnh: **`k6/DISTRIBUTED.md`** mục 2 — `cart-contention.js` chạy 2 lần
      (gate OFF vs ON, cùng tồn kho), so sánh p95, 429/5xx, `SHOW PROCESSLIST`, busy workers.
      *(Việc chạy thật trên staging scale = vận hành, chưa chạy.)*
- [x] **Verify oversell + gate-leak**: `k6/verify-oversell.sql` (bất biến DB + công thức
      `gate còn + đã bán + hold sống = seed`) + `flash-gate:status` đọc drift.
- [x] Cập nhật `docs/SCALE-30K.md` mục C + ưu tiên 6.

---

## Ngoài phạm vi (ghi nhận)

- **Cloudflare Waiting Room**: thay thế/bổ sung ở tầng edge khi flash-sale vượt xa mức
  Redis gate xử lý (trả phí, cấu hình ngoài repo). Chỉ cân nhắc nếu k6 Phase 4 cho thấy
  gate + retry vẫn không đủ.
- Mẫu 4-5 chống over-redeem coupon (claim-wallet/Redis gate) — tái dùng `FlashGateService`
  sau khi pattern này chạy ổn.

## Thứ tự làm & phụ thuộc

1 (core) → 2 (vận hành) → 3 (độc lập, làm song song được) → 4 (cần 1+2+3 và staging scale đang chạy).
Ước lượng file đụng tới: `StockService.php`, `FlashGateService.php` (mới), `config/flash_gate.php` (mới),
3 command (mới), `routes/console.php`, `deploy/mysql/*.cnf`, `docker/mysql/*`, test + k6.
