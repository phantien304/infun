# Changelog — 2026-07-20: Điều tra hiệu năng staging + fix k6

Bối cảnh: chuẩn bị bắn k6 `TARGET=30000` theo `docs/SCALE-30K.md`, chuyển stack
`docker-compose.staging.yml` sang máy để bàn riêng (12 luồng / 32GB, qua Docker
remote context) để tách khỏi máy dev đang chạy dev stack + Herd. Test đầu tiên ở
mức `TARGET=50` cho fail rate 26-39% dù server nhìn "nhàn" — đi tìm nguyên nhân
mất khá nhiều vòng, tổng hợp lại đây để không phải lặp lại quy trình dò từ đầu.

**Kết luận quan trọng nhất: phần lớn "fail" ban đầu KHÔNG phải do server chậm.**
2 trong 3 nguyên nhân chính nằm ở chính script k6 (`k6/mixed-30k.js`), không
phải ở app hay hạ tầng. Xem mục 4-5.

---

## 1. Hạ tầng test — Docker remote context sang máy để bàn

Máy để bàn cạnh (12 luồng/32GB, IP LAN `192.168.1.11`) dùng làm staging riêng,
tách khỏi máy dev chính (đang chạy dev stack + Herd cùng lúc → tự nó là 1 nguồn
nhiễu, xem mục 4).

```bash
# Trên máy để bàn (PowerShell Admin), 1 lần:
netsh interface portproxy add v4tov4 listenport=2375 listenaddress=0.0.0.0 connectport=2375 connectaddress=127.0.0.1
# (Docker Desktop "Expose daemon on tcp://localhost:2375" mặc định chỉ bind
# 127.0.0.1 dù tick chọn — cần portproxy để expose ra LAN)

# Trên máy dev, add context:
docker context create staging-desktop --docker "host=tcp://192.168.1.11:2375"

# Build local rồi transfer thẳng (nhanh + ổn định hơn build qua remote context —
# BuildKit qua session dài dễ bị "CANCELED" ở mốc 60s khi đường truyền không ổn định):
docker compose -f docker-compose.staging.yml build infun-php
docker save infun-app:staging | docker --context staging-desktop load
docker --context staging-desktop compose -f docker-compose.staging.yml up -d --scale infun-php=3
```

DB: copy full (schema + data) từ MySQL dev sang MySQL staging remote qua pipe
2 context cùng lúc (không cần export ra file trung gian):

```bash
docker exec infun-mysql mariadb-dump -uroot -proot --single-transaction --quick infun \
  | docker --context staging-desktop exec -i infun-mysql-staging mariadb -uroot -proot infun
```

`docker save | docker load` qua context remote **đôi khi bị đứt kết nối giữa
chừng** (`wsasend: An existing connection was forcibly closed`) — không phải lỗi
logic, thử lại là qua. Luôn `docker --context staging-desktop images infun-app:staging`
so Image ID với local sau mỗi lần transfer để chắc chắn container đang chạy
đúng image mới (nếu transfer fail nhưng `up -d` không thấy đổi ID thì compose
sẽ KHÔNG recreate container → chạy nhầm code cũ mà không báo lỗi).

---

## 2. `HasSchemaCache` — N+1 Redis (đã fix)

`app/Models/Traits/HasSchemaCache.php`

**Đo được**: 1 request `GET /san-pham?page=1` sinh ra **4530 lệnh Redis**, trong
đó 4434 (98%) là `GET schema:infun:mysql:<table>` — cùng 1 bảng (category,
manufacturer, zone...) bị gọi lại hàng trăm lần trong CÙNG 1 request. Nguyên
nhân: `getTableColumnAndTypeList()` gọi `cache()->get($key)` — round-trip Redis
qua mạng — **mỗi lần được gọi**, không có bộ nhớ đệm cấp process. Vì 135/136
model không khai báo `$fillable`, mỗi model instance (mỗi ROW hydrate ra 1
object mới) tự gọi lại hàm này.

**Fix**: thêm `protected static array $schemaCacheMemo = []` — check memo
trước khi chạm Redis, ghi vào memo sau khi có kết quả (từ cache hoặc DB). Memo
reset mỗi request mới (php-fpm), không ảnh hưởng invalidation.

**Kết quả đo lại**: 4530 → 121 lệnh Redis/request (giảm 97.3%), mỗi bảng giờ
chỉ 2 lệnh GET (trước đó hàng trăm).

---

## 3. Cache `Collection<Model>` → mảng thuần (đã fix)

`app/Repositories/Concerns/CacheableRepository.php` — thêm
`rememberSystemModels()`.

Sau khi giảm round-trip Redis, latency browse dưới tải vẫn cao — nghi CPU cost
`unserialize()` khi PHP tái tạo hàng trăm Eloquent model object (category,
manufacturer, filter, zone — "5 tài nguyên load mọi page render" theo
CLAUDE.md) từ cache Redis mỗi request.

**Fix**: `rememberSystemModels()` cache **mảng attributes + relations** (không
phải object graph đầy đủ), rehydrate lại bằng `newFromBuilder()` (rẻ hơn nhiều
so với `unserialize()` vì chỉ gán thẳng attributes, không tái tạo toàn bộ state
nội bộ Eloquent). Trả về đúng `Collection<Model>` như cũ — **0 thay đổi ở phía
tiêu thụ** (blade/controller/DTO).

Áp dụng cho 4 repo (KHÔNG áp `menu` — cache đó vốn đã là mảng HTML pre-render,
không phải Model):
- `CategoryRepository::listAllCached()`
- `ManufacturerRepository::listAllCached()`
- `FilterRepository::listAllCached()`
- `ZoneRepository::listAllCached()`

**Verify** (qua `php artisan tinker` trên dev): so `RAW` (Eloquent thuần) vs
`CACHE-MISS` (lần đầu, chạy resolver) vs `CACHE-HIT` (lần 2, đọc từ Redis) —
cả 3 ra dữ liệu **khớp tuyệt đối** (title, slug, relation `description` đều
đúng), cả 4 repo đều pass.

---

## 4. Index thiếu cho `product.date_available` (đã fix — migration)

`database/migrations/2026_07_20_000000_add_date_available_index_to_product.php`

Bắt bằng MySQL slow query log (`SET GLOBAL slow_query_log='ON'; SET GLOBAL
long_query_time=0;` trong lúc chạy k6, sau đó `mysqldumpslow -s t`): 1 pattern
query — `COUNT(*)` phân trang của trang list sản phẩm (scope `dateAvailable`,
`WHERE (date_available <= ? OR date_available IS NULL) AND deleted_at IS
NULL`) — full table scan **491,840 dòng MỖI LẦN GỌI**, 367 lần trong 1 cửa sổ
k6 90 giây → tổng **31 giây CPU DB** (`EXPLAIN`: `type: ALL`). Mọi query khác
trong log tổng cộng chưa tới 1 giây.

Index cũ `idx_product_list (deleted_at, created_at)` không cover được
`date_available` nên MySQL bỏ qua, full scan.

**Fix**: thêm index `(date_available, deleted_at)`. `EXPLAIN` sau fix:
`type: ALL` → `type: range` + `Using index` (chỉ đọc index, không đọc full
row). Timing: 0.08s → 0.05s/query (~37% nhanh hơn) — vẫn quét ~246k dòng vì
đa số sản phẩm thật sự thoả điều kiện "đang bán", không tránh được hoàn toàn
bằng index, nhưng đỡ hẳn so với full table scan.

---

## 5. Máy chạy k6 chính là nguồn nhiễu lớn nhất (phát hiện quan trọng)

Sau 3 fix trên, fail rate ở `TARGET=50` **không cải thiện rõ** qua nhiều lần
chạy lại (29.7% → 36% → 32% → 39%, dao động lớn không theo fix nào) — nghi máy
đang bắn k6 (máy dev chính) chính là điểm nghẽn, vì suốt phiên nó còn chạy
song song Docker Desktop (dev stack 9 container) + Herd + mọi thứ khác.

**Verify**: chạy k6 bằng container `grafana/k6` **ngay trên máy remote**
(cùng Docker network với staging, không qua LAN/máy dev):

```bash
cat k6/mixed-30k.js | docker --context staging-desktop run --rm -i \
  --network infun_infun-net grafana/k6 run \
  -e BASE_URL=http://infun-web-staging:80 -e TARGET=50 -e DURATION=1m \
  -e PRODUCT_IDS=... -e FLASH_PRODUCT_ID=3 -e ZONE_ID=... -e DISTRICT_ID=... -e WARD_ID=... -
```

(dùng `k6 run -` đọc script từ stdin — bind-mount `-v` không hoạt động vì path
thuộc máy dev, không tồn tại trên daemon remote.)

**Kết quả**: browse p95 từ **8-17 GIÂY** (đo qua LAN từ máy dev) xuống còn
**157ms** (đo nội bộ trên máy remote) — nhanh hơn ~100 lần. Máy dev (LAN +
Windows Defender/Docker Desktop port-forward + tải song song) là nguồn nhiễu
áp đảo, không phản ánh khả năng thật của server.

**Bài học cho lần sau**: KHÔNG bắn k6 từ máy dev đang chạy nhiều thứ khác. Chạy
k6 làm container ngay trên máy target (hoặc 1 máy load-generator riêng, sạch),
đúng tinh thần "k6 phân tán" mà `docs/SCALE-30K.md` mục D đã ghi.

---

## 6. Bug thật trong `k6/mixed-30k.js` + `k6/cart-contention.js` (đã fix)

Sau khi cô lập được máy chạy k6, `http_req_failed` vẫn ~26-27% dù latency đã
rất tốt — tức các request **fail thật (status lỗi), không phải timeout**. Thêm
`console.log` tạm vào từng nhánh phân loại status để bắt chính xác.

### 6a. Sai URL — thiếu prefix `checkout/`

Route thật (`routes/web.php:46`): `Route::prefix('checkout')->group(...
Route::post('add-to-cart', ...))` → URI đầy đủ là **`checkout/add-to-cart`**.

Script cũ gọi `${BASE_URL}/add-to-cart` (thiếu `checkout/`) → khớp nhầm route
khác (GET-only) → **405 Method Not Allowed** cho MỌI lần add-to-cart. Đây là
nguồn `ADD_FAIL` lớn nhất (204/1959 request ở 1 lần đo, và lan sang
`checkout()`/`flashSale()` vì cả 2 đều gọi `postAdd()` trước khi checkout).

Đã sửa `postAdd()` trong cả 2 file dùng đúng `${BASE_URL}/checkout/add-to-cart`.

### 6b. CSRF token cache cả đời VU → 419 giữa chừng

`csrf()` gọi `GET /` lấy token **1 lần**, cache theo `csrfByVu[__VU]` cho suốt
đời VU (~2.5 phút, hàng chục request). Khi session/token phía server đổi giữa
chừng (session TTL, redis session store...), token cũ không còn khớp →
**419 CSRF token mismatch** — không được phân loại vào counter nào (chỉ check
201/422/429/5xx), rơi vào `http_req_failed` một cách âm thầm.

Đã sửa: tách `refreshCsrf()` (luôn fetch mới) khỏi `csrf()` (đọc cache), thêm
retry-1-lần khi gặp 419 trong `postAdd()`/`checkoutCod()` (mixed-30k.js) và
trong hàm add (cart-contention.js) — đúng hành vi 1 browser thật (trang nào
cũng có token hiện hành, không cache xuyên session).

### Kết quả sau khi sửa cả 2 bug

Verify bằng bản debug (log mọi status không khớp 2xx/422/429/5xx) — **0
unclassified failure** ở cả `add`/`order`/`flash`. Toàn bộ phần "fail" còn lại
(~14-18% tuỳ TARGET) là **422 hợp lệ** (từ chối nghiệp vụ đúng — vd hết suất
flash sale, bằng chứng chống oversell hoạt động đúng), không phải lỗi.

---

## 7. Kết quả capacity cuối cùng (đo sạch, sau toàn bộ fix)

Chạy `k6/mixed-30k.js` (bản đã fix) trực tiếp trên máy remote, DURATION=1m:

| TARGET | Browse p95 | Add p95 | Order p95 | 5xx | Ghi chú |
|---|---|---|---|---|---|
| 50 | 165ms | 37ms | 54ms | 0 | Toàn bộ threshold latency PASS |
| 300 (255 VU browse) | 312ms | 62ms | 109ms | 0 | Toàn bộ threshold latency PASS |
| 1500 (~1275 VU browse) | chạm trần 60s (p95) | chạm trần 60s (p95) | 167ms (PASS) | 0 | Bắt đầu queue thật — CPU mid-test ~11.4/12 lõi |

**Không có lỗi 5xx ở bất kỳ mức nào** — hệ thống suy giảm graceful (queue/chậm
dần), không sập/crash/deadlock. Order/flash (luồng ghi) vẫn vững ngay cả ở
TARGET=1500 (`order_ok=121, flash_ok=369, order_error=0, flash_error=0`).

**Ngưỡng thật cho 1 máy đơn (12 lõi/32GB, 3 php-fpm replica × `pm.max_children=25`)**
nằm đâu đó giữa 300 và 1500 target — browse (đọc) là nhánh chạm giới hạn trước,
không phải write path. Muốn lên xa hơn (hướng "vài nghìn in-flight" mà
`docs/SCALE-30K.md` mô tả cho 30k active) cần scale ngang (thêm app server,
DB read replica) đúng kiến trúc đã vạch trong `docs/SCALE-30K.md`, không phải
tối ưu thêm trên 1 máy.

---

## Việc còn nợ / gợi ý bước tiếp

- [ ] Commit 3 fix app-level (mục 2-4) + 2 fix k6 script (mục 6) — đang nằm
      trong working tree, chưa commit.
- [ ] `http_req_failed: ['rate<0.02']` trong threshold của `mixed-30k.js` hơi
      quá chặt cho kịch bản có `flash_sale` (cố tình tạo tranh chấp tồn kho →
      422 là kết quả ĐÚNG, không phải lỗi) — cân nhắc tách riêng ngưỡng cho
      browse/add/order (không tính 422) thay vì 1 ngưỡng chung cho tất cả HTTP.
- [ ] Chưa test `TARGET` ở khoảng giữa 300-1500 để định vị chính xác ngưỡng
      queue bắt đầu (hiện chỉ có 2 điểm dữ liệu quanh ngưỡng).
- [ ] Muốn tiến gần hơn tới kịch bản "30k active" thật cần k6 phân tán
      (k6-operator/Grafana Cloud) + nhiều app server thật, theo đúng
      `docs/SCALE-30K.md` mục D — máy đơn không đại diện cho hạ tầng multi-server.
- [ ] `docker save | docker load` qua remote context thỉnh thoảng đứt kết nối
      giữa chừng — nếu làm quy trình deploy lặp lại thường xuyên, nên cân nhắc
      dùng registry riêng (vd 1 registry local) thay vì save/load tay.
