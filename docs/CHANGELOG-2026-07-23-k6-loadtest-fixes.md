# CHANGELOG 2026-07-23 — k6 load test staging: PK race condition + Redis OOM + capacity ceiling

> Bối cảnh: build lại image `infun-php` trên staging thật (192.168.1.11,
> `docker context staging-ssh`) rồi bắn `k6/mixed-30k.js` nhiều đợt để tìm
> điểm gãy. Phát hiện 1 bug logic thật (không phải giới hạn hạ tầng) + 1 config
> sai + xác định trần CPU thật của máy staging hiện tại.

---

## 1. Bug thật — race condition sinh PK, KHÔNG atomic (đã fix)

**Phát hiện qua**: `k6 run -e TARGET=3000 k6/mixed-30k.js` bắn **từ trong
network Docker của staging** (container `grafana/k6` join `infun_infun-net`,
gọi thẳng `http://infun-web` — loại bỏ nhiễu máy dev/LAN, xem mục 3) → 328 lỗi
5xx thật khi đặt hàng (`order_error`), log:

```
PDOException: SQLSTATE[23000]: Integrity constraint violation: 1062
Duplicate entry '1610' for key 'PRIMARY'
insert into `orders` (`id`, ...) values (1610, ...)
```

**Root cause** — `app/Models/Base/Base.php` (`getNextInsertId()` +
`insertAndSetId()`): đọc `SHOW TABLE STATUS ... Auto_increment` (giá trị "next
id" DỰ KIẾN) bằng 1 SELECT riêng, rồi INSERT tường minh giá trị đó — 2 bước
KHÔNG atomic. Dưới tải đồng thời cao, 2 transaction cùng đọc trùng "next id"
trước khi bên nào commit → cả 2 cùng INSERT trùng id → 1 thắng, 1 vỡ
`Duplicate entry`. Bug áp dụng cho MỌI model insert dùng `Base` khi PK rỗng
lúc insert, không riêng `orders` — chỉ hiếm gặp ở tải thấp vì cửa sổ race hẹp.

**Fix**: bỏ hẳn giá trị PK rỗng khỏi `$attributes` thay vì tự tính, để DB tự
AUTO_INCREMENT (mysql/mariadb) / rowid (sqlite) cấp phát atomic;
`parent::insertAndSetId()` đọc lại giá trị thật qua `lastInsertId()`. Giữ
nguyên hợp đồng cũ "id rỗng/0 = tạo mới" cho mọi caller hiện có (vd
`CreateOrderService::buildOrderRow 'id' => 0`) — chỉ đổi CÁCH lấy id, không
đổi input contract, không cần sửa gì ở tầng gọi.

**Verify**: build lại image, chạy lại ĐÚNG bài TARGET=3000 đã phát hiện lỗi →
`order_error=0`, `Duplicate entry` không phát sinh thêm (giữ nguyên 258 dòng
log cũ từ trước fix). Xác nhận vững tới TARGET=5000 (xem mục 2) — không có
Duplicate entry mới dù tải cao hơn nhiều.

---

## 2. Config sai — Redis `maxmemory` quá nhỏ cho traffic test (đã nâng)

Ở TARGET=5000 (sau khi đã fix mục 1), lỗi 5xx tái xuất hiện (`order_error`
=1206, `flash_error`=483) nhưng log cho thấy nguyên nhân KHÁC hẳn:

```
RedisException: OOM command not allowed when used memory > 'maxmemory'.
```

`docker-compose.staging.yml` cấu hình Redis `maxmemory=512mb` +
`maxmemory-policy=noeviction` — khi đầy, Redis **từ chối mọi lệnh ghi**
(session/cache/queue) thay vì evict key cũ → cascading lỗi ở luồng checkout.
`used_memory` đo được lúc đó: 511.96M/512M (gần như 100%).

**Fix (quyết định giữ `noeviction`, chỉ nâng trần)**: `maxmemory` 512mb →
**1gb**. Không đổi `maxmemory-policy` — quyết định của chủ dự án, chấp nhận
trade-off "hết bộ nhớ thì lỗi rõ ràng" thay vì evict âm thầm.

**Verify**: chạy lại TARGET=5000 (4 replica, xem mục 3) → **0** lỗi Redis OOM,
`used_memory` ổn định ~546M/1G trong suốt test.

---

## 3. Bài học phương pháp — bắn k6 từ ĐÂU quan trọng ngang bắn cái gì

2 lần đầu bắn k6 **từ máy dev** (qua LAN, `BASE_URL=http://192.168.1.11:8100`)
cho kết quả cực xấu (browse p95 55-60s, failure 27-38%) — tưởng là staging quá
tải. Bắn lại **cùng TARGET=900 từ container `grafana/k6` chạy TRONG network
Docker của chính staging** (`--network infun_infun-net`, gọi thẳng
`http://infun-web`, bỏ qua hoàn toàn máy dev + LAN/NAT) → p95 giảm 250-450 lần
(browse 134ms, add 204ms, order 259ms). Kết luận: 2 lần đầu đo SAI đối tượng —
đang đo máy dev bận việc khác + đường mạng, không phải năng lực thật của
staging. **Từ đó về sau mọi lần bắn đều chạy trong network staging.**

Lệnh mẫu (không cần cài k6 trên máy staging):

```bash
cat k6/mixed-30k.js | docker --context staging-ssh run -i --rm \
  --network infun_infun-net \
  -e BASE_URL=http://infun-web \
  -e TARGET=<N> -e DURATION=3m \
  -e PRODUCT_IDS=3,6,9,14,17,19,21,27 -e FLASH_PRODUCT_ID=100 \
  -e CATEGORY_IDS=$(python -c "print(','.join(str(i) for i in range(1,51)))") \
  -e ZONE_ID=4240 -e DISTRICT_ID=1 -e WARD_ID=1 \
  grafana/k6 run -
```

`ZONE_ID=4240/DISTRICT_ID=1/WARD_ID=1` là chuỗi zone→district→ward khớp nhau
thật trong DB staging (default `ZONE_ID=230` của script KHÔNG khớp
`DISTRICT_ID=1` → checkout luôn reject địa chỉ sai). Trước mỗi đợt bắn có
tranh chấp tồn kho (`FLASH_PRODUCT_ID`), PHẢI chạy
`php artisan k6:provision-stock --flash=100` reset tồn — nếu không `flash_ok`
đợt sau luôn 0 vì tồn đã cạn từ đợt trước.

---

## 4. Trần capacity thật — CPU-bound, không phải worker-count-bound

Đo trực tiếp CPU/RAM host qua SSH (`Get-CimInstance Win32_OperatingSystem` +
`Win32_Processor`) trong lúc TARGET=5000 đang chạy (2 mẫu cách nhau vài phút):

| | Mẫu 1 | Mẫu 2 |
|---|---|---|
| CPU `LoadPercentage` | 99% | 100% |
| CPU tức thời (`Get-Counter`) | 99.6% | 98.8% |
| RAM used | 21.75/31.77 GB (68.5%) | 21.91/31.77 GB (69%) |

Host: **Intel i5-12400, 6 core / 12 luồng logic**. CPU bão hoà HOÀN TOÀN và
LIÊN TỤC (không phải spike) ở TARGET=5000 — kể cả sau khi tăng `infun-php` từ
3 → **4 replica** (240 worker). RAM còn dư nhiều (~10GB), không phải nút thắt.

Breakdown container lúc đỉnh (`docker stats`) — tổng CPU% ≈ 1130% (~11.3/12
luồng): 4× `infun-php` (140-189% mỗi container), 2 MySQL replica (127-175%
mỗi cái — replay ghi cũng nặng CPU không kém app), Redis 70%, ProxySQL 37%,
Meilisearch 29%, nginx 17%, **container `k6` sinh tải cũng ăn ~33%** — tự nó
là 1 phần của tải đang đo, không tách biệt khỏi máy được đo.

**Kết luận**: thêm replica `infun-php` KHÔNG giúp vượt TARGET=5000 vì nút thắt
không phải "số worker" mà là **trần CPU vật lý của 1 máy duy nhất** đang cõng
cả app + DB (master+2 replica) + cache + search + cả tải sinh k6 cùng lúc.
Muốn vượt ngưỡng này cần tách hạ tầng (nhiều máy) hoặc nâng core, không phải
tăng `pm.max_children`/replica.

### Sau khi fix mục 1+2, TARGET=5000 vẫn nghẽn NHƯNG không còn SAI

| | TARGET=5000 trước fix (3 replica, Redis 512MB) | TARGET=5000 sau fix (4 replica, Redis 1GB) |
|---|---|---|
| `order_error` | 1206 | **0** |
| `flash_error` | 483 | **0** |
| `http_req_failed` | 74.41% | 16.76% |
| `cache_hit` | 24.49% | 81.94% (qua threshold) |
| Oversell (flash stock) | không | không (cả 2 lần) |

Vẫn còn timeout do CPU nghẽn (browse/order p95 chạm trần 60s) — **chấp nhận
được ở mức "chậm nhưng đúng"**, khác hẳn trạng thái "vừa chậm vừa sai dữ liệu"
trước fix.

### TARGET an toàn cho máy staging hiện tại (1 máy, không tách hạ tầng)

- **~3000 VU**: ổn định thật, `order_error=0`, chỉ hơi chậm ở đỉnh tải
  (worker pool 180 gần bão hoà nhưng chưa kill request).
- **~5000 VU**: vượt trần CPU máy — vẫn đúng dữ liệu (nhờ fix mục 1+2) nhưng
  chậm nặng (nhiều request client timeout 60s).
- Muốn test > 5000 VU có ý nghĩa: bắt buộc tách container k6 sang máy khác
  (nó đang tự ăn CPU của chính máy được đo) và/hoặc scale hạ tầng ra nhiều máy
  thay vì thêm replica trên cùng 1 host.

---

## 5. Cấu hình chuẩn đã chốt trong `docker-compose.staging.yml`

| Service | Trước | Sau (chuẩn mới) |
|---|---|---|
| `infun-php` | scale mặc định 1 (phải nhớ `--scale infun-php=3`) | `deploy.replicas: 4` — mặc định 4 dù `up -d` KHÔNG kèm `--scale` (`--scale` khi chạy vẫn override được) |
| `redis` | `--maxmemory 512mb` | `--maxmemory 1gb` (giữ nguyên `--maxmemory-policy noeviction`) |

> ⚠️ **Chưa verify được `deploy.replicas: 4` có thật sự áp dụng khi `up -d`
> KHÔNG kèm `--scale`** — máy staging tắt giữa chừng lúc đang test lại (SSH
> connection timed out, xác nhận là do máy tắt chứ không phải lỗi mạng). Cần
> chạy lại `docker --context staging-ssh compose -f docker-compose.staging.yml
> up -d --no-deps infun-php` (KHÔNG kèm `--scale`) rồi `docker --context
> staging-ssh ps | grep infun-php` đếm đúng 4 container ở lần bật máy kế tiếp.

---

## Việc còn nợ

- [ ] Verify `deploy.replicas: 4` có hiệu lực không cần `--scale` (mục 5).
- [ ] Muốn test thật > 5000 VU: tách k6 load-gen sang máy khác ngoài staging
      host (xem mục 4) — theo đúng khuyến nghị gốc trong `k6/DISTRIBUTED.md`.
- [ ] Cân nhắc lại `maxmemory-policy=noeviction` nếu sau này traffic thật gây
      OOM thường xuyên ở 1GB — `allkeys-lru` sẽ an toàn hơn cho use-case
      cache/session/queue nhưng đã là quyết định có chủ đích giữ nguyên lần này.
- [ ] `docs/STAGING.md` mục "Lệnh hay dùng" nên thêm ví dụ bắn k6 trong-network
      (mục 3) làm cách chuẩn thay vì bắn từ máy dev qua LAN.
