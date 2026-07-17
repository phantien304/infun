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

## Phase 1 — Core gate (code chính)

- [ ] **`config/flash_gate.php`**: `enabled` (env `FLASH_GATE_ENABLED`), `redis_connection`
      (default — KHÔNG phải `cache`), prefix key `gate:stock:{variant_id}`, log channel.
- [ ] **`app/Services/Stock/FlashGateService.php`**:
  - `tryAcquire(int $variantId, int $qty): ?bool` — Lua atomic
    `if GET >= qty then DECRBY, return 1 else return 0`; key không tồn tại → `null` (variant
    không gated, đi đường thường). Lua để tránh race DECR-âm-rồi-INCR-trả.
  - `release(int $variantId, int $qty)` — `INCRBY` trả suất (chỉ khi key còn tồn tại,
    cũng Lua để không hồi sinh key đã teardown).
  - `isGated`, `remaining` (đọc cho command/monitor).
  - Mọi lỗi Redis: catch → log warning → coi như not-gated (fail-open).
- [ ] **Hook vào `StockService::reserveCheckout`** — pre-pass TRƯỚC `transaction()`:
  1. Với mỗi item gated: tính **delta** so với hold hiện có (`reservationsForVariant`) —
     tăng qty mới debit phần tăng, giảm qty thì credit; tránh double-debit khi user bấm lại.
  2. `tryAcquire` từng item; fail → `release` lại các item đã debit trong pre-pass, trả
     `['ok'=>false, 'failed'=>…]` y như `ReservationUnavailable` (UI hiện tại dùng lại được).
  3. Pre-pass OK → vào transaction DB như cũ; nếu DB reservation vẫn fail
     (`ReservationUnavailable`) → credit lại toàn bộ delta đã debit.
- [ ] **Credit khi nhả hold** — `releaseReservationRow()` (dùng chung cho `releaseHolder`,
      `releaseExpired`, hold hết hạn): sau khi trừ `reserved` thành công, `release(variant, qty_đã_nhả)`.
      `deductForOrder` (hold → bán thật) KHÔNG credit — suất đã tiêu thụ đúng.

## Phase 2 — Vận hành gate (seed / reconcile / teardown)

- [ ] **`artisan flash-gate:seed {variantId...}`**: trong transaction + lock stock row,
      tính `sellable = Σ max(0, on_hand − reserved)` các kho sellable → `SET gate:stock:{v}`.
      In ra giá trị seed. (Chạy ngay trước giờ sale.)
- [ ] **`artisan flash-gate:status`** / **`flash-gate:teardown {variantId...|--all}`**
      (teardown = DEL key → variant về đường DB thường).
- [ ] **`artisan flash-gate:reconcile`** + schedule `everyMinute()->withoutOverlapping()`
      trong `routes/console.php`, chỉ chạy khi có key gate: với mỗi variant gated, tính lại
      `db_sellable` (KHÔNG lock, đọc replica được) và **clamp xuống**:
      `gate = min(gate, db_sellable)` bằng Lua. Chỉ clamp xuống — không tự nâng lên
      (nâng do CMS nhập thêm hàng thì chạy lại `seed`). Log drift > ngưỡng.
- [ ] Case CMS đổi tồn giữa sale (`updateOnHand`): ghi chú vận hành — sau khi sửa tồn
      variant đang gated phải chạy `flash-gate:seed` lại (reconcile chỉ tự xử lý chiều giảm).

## Phase 3 — Chống 5xx khi lock vẫn dồn (mục C còn lại)

- [ ] `innodb_lock_wait_timeout` = 5–10s: `deploy/mysql/master.cnf` + `docker/mysql/` (staging).
- [ ] **Retry khi lock timeout** (SQLSTATE HY000/1205, deadlock 40001/1213): wrap transaction
      trong `reserveCheckout` + `deductForOrder` — tối đa 2 retry, sleep jitter 50–150ms;
      hết retry → lỗi thân thiện "hệ thống đang quá tải, thử lại" chứ không 5xx.
- [ ] Rà TTL hold (`cart_applied_ttl_minutes`) cho kịch bản sale (TTL ngắn hơn?) —
      `stock:release-expired` đã `everyMinute()`, cân nhắc tăng `limit` mặc định 500 khi sale.

## Phase 4 — Kiểm chứng

- [ ] Unit test (Pest): Lua debit/credit đúng floor 0; delta khi đổi qty; credit khi hold
      expire; fail-open khi Redis chết; reconcile chỉ clamp xuống.
- [ ] **k6 `cart-contention.js`** trên staging scale (LB + replica): chạy 2 lần
      (gate OFF vs ON, cùng tồn kho) — so sánh p95 save-order, tỷ lệ 429/5xx,
      `SHOW PROCESSLIST` (số thread chờ lock), busy php-fpm workers.
- [ ] **Verify oversell + gate-leak** sau test (SQL + Redis):
      `Σ qty đơn thành công ≤ tồn ban đầu`; `reserved ≤ on_hand`; `on_hand ≥ 0`;
      và `gate còn lại + đã bán + hold sống = giá trị seed` (lệch = leak credit/debit).
- [ ] Cập nhật `docs/SCALE-30K.md` mục C + ưu tiên 6 khi xong.

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
