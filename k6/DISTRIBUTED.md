# k6 phân tán — đo 30k user active trên staging (SCALE-30K mục 7)

> Một máy chạy k6 KHÔNG kéo nổi 30k VU (RAM ~1-4KB/VU nhưng CPU + socket là trần
> thật; thực tế 1 máy 8 core kéo ổn ~5-8k VU script này). 30k thật = chạy phân tán.
> Đích đến: xác nhận topology staging (LB + 3 php + master/2 replica + ProxySQL +
> 2 Redis) chịu được mô hình traffic 30k active mà KHÔNG oversell, không 5xx.

---

## 0. Chọn cách chạy phân tán

| Cách | Khi nào | Ghi chú |
|---|---|---|
| **Grafana Cloud k6** (khuyến nghị) | Nhanh nhất, không dựng hạ tầng | `k6 cloud run --vus ... k6/mixed-30k.js`. Free tier đủ cho vài lần thử nhỏ; 30k VU cần plan trả phí. Load gen nằm NGOÀI mạng → staging phải reachable từ internet (bảo vệ bằng IP allowlist/Basic Auth ở LB). |
| **k6-operator trên k8s** | Đã có cluster k8s | CRD `TestRun`, `parallelism: N` tự chia VU cho N pod. Chạy được trong mạng nội bộ. |
| **Thủ công N máy** | Không có 2 cái trên | N VPS cùng chạy `k6 run -e TARGET=30000/N ...`, cộng tay kết quả (hoặc đẩy chung về InfluxDB/Prometheus qua `--out`). Rẻ, đủ dùng. |

Script không phải sửa gì — mô hình chia theo `TARGET`:
mỗi máy/pod chạy `TARGET = 30000 / số_máy` (script tự chia 85/9/3/2% các nhánh).

## 1. Chuẩn bị staging (BẮT BUỘC — không chạy trên local/prod)

```bash
# Stack staging giống prod:
docker compose -f docker-compose.yml -f docker-compose.lb.yml -f docker-compose.scale.yml \
  up -d --build --scale infun-php=3
```

.env staging cho đợt test:

```bash
THROTTLE_ADD_TO_CART=100000     # không nới thì chỉ đo được 429
THROTTLE_SAVE_ORDER=100000
MAIL_MAILER=log                 # mỗi order dispatch mail job
# payment: script chỉ dùng payment_code=cod — không đụng ZaloPay
```

Seed dữ liệu test:

```sql
-- SP flash: tồn nhỏ, policy Deny, 1 variant default
UPDATE product_stock ps JOIN product_variant pv ON pv.id = ps.product_variant_id
SET ps.on_hand = 500, ps.reserved = 0
WHERE pv.product_id = <FLASH_PRODUCT_ID> AND pv.is_default = 1;
-- Ghi lại giá trị on_hand này → @initial_on_hand trong verify-oversell.sql
```

Sau khi đổi env: `php artisan config:clear && config:cache` + reload FPM (OPcache
`validate_timestamps=0`).

## 2. Ma trận chạy (mục 6 Phase 4: so sánh gate OFF vs ON)

Chạy `cart-contention.js` 2 lần CÙNG điều kiện (reset tồn giữa 2 lần):

```bash
# Lần 1 — gate OFF (baseline đường DB thuần)
FLASH_GATE_ENABLED=false → config:cache, reset tồn
k6 run -e BASE_URL=http://staging -e PRODUCT_ID=<id> -e VUS=1000 k6/cart-contention.js

# Lần 2 — gate ON
FLASH_GATE_ENABLED=true → config:cache, reset tồn
php artisan flash-gate:seed <variant_id>          # NGAY trước khi bắn
k6 run -e BASE_URL=http://staging -e PRODUCT_ID=<id> -e VUS=1000 k6/cart-contention.js
php artisan flash-gate:status <variant_id>        # xem drift ngay sau test
```

So sánh: p95 `add_to_cart` / `save-order`, tỷ lệ 5xx (phải = 0), số 422 (từ chối
đúng), `SHOW PROCESSLIST` (thread chờ lock phải giảm mạnh khi gate ON), busy
php-fpm workers. Kỳ vọng: gate ON → request bị từ chối ngay từ Redis, KHÔNG còn
hàng dài thread `Waiting for row lock`.

Sau đó chạy hỗn hợp 30k (gate ON, flash variant đã seed):

```bash
k6 run -e BASE_URL=http://staging -e TARGET=30000/N -e PRODUCT_IDS=... \
       -e FLASH_PRODUCT_ID=<id> -e ZONE_ID=... k6/mixed-30k.js   # trên N máy
```

Lưu ý cache_page (mục 5) đã bật lại: nhánh browse chủ yếu hit Redis → p95 rất
thấp là ĐÚNG. Muốn đo worst-case (cache lạnh), flush trước khi bắn:
`php artisan tinker --execute="\App\Helpers\CacheGate::flushPages();"`.

## 3. Quan sát trong lúc chạy (mỗi phút)

```bash
# DB: thread chờ lock + lag replica
mysql -h <master> -e "SHOW PROCESSLIST" | grep -c "Waiting for"
mysql -h <replica> -e "SHOW SLAVE STATUS\G" | grep Seconds_Behind

# ProxySQL: pool + phân phối query
mysql -h127.0.0.1 -P6032 -uradmin -pradmin \
  -e "SELECT hostgroup, srv_host, status, ConnUsed, Queries FROM stats_mysql_connection_pool;"

# php-fpm: busy workers (đường /fpm-status nếu đã bật trong pool)
# Redis gate: suất còn lại giảm dần về 0 rồi ĐỨNG YÊN (không âm, không hồi)
php artisan flash-gate:status
# App: 429/5xx + fail-open của gate
tail -f storage/logs/laravel.log | grep -Ei "FlashGate|deadlock|lock wait"
```

## 4. Verify sau test

1. `mysql ... < k6/verify-oversell.sql` (sửa `@variant_id`, `@initial_on_hand`
   trước) — 4 nhóm bất biến DB + hướng dẫn đối soát gate-leak ở cuối file.
2. `php artisan flash-gate:status <variant>` — bất biến:
   `gate còn + tổng đã bán + hold sống = giá trị seed`. Chờ hold hết TTL
   (`stock:release-expired` chạy mỗi phút) rồi đo lại lần cuối.
3. Đối chiếu counter k6: `add_ok × QTY ≤ tồn ban đầu`, `order_ok` khớp số đơn
   trong DB, `add_error/order_error = 0`.
4. Dọn: truncate các bảng orders*, `flash-gate:teardown --all`, reset tồn.

## 5. Ngưỡng đạt (thresholds đã khai trong script)

- 5xx = 0 (kể cả nhánh flash — hết hàng phải là 422/từ chối sạch, không lỗi).
- p95 add-to-cart < 2s dưới đỉnh tranh chấp; browse p95 < 800ms (cache ấm).
- Không oversell (verify-oversell.sql sạch), gate không leak (bất biến khớp).
- Replica lag đỉnh < 10s (quá là ProxySQL tự SHUN — xem nó có nhả lại không).
