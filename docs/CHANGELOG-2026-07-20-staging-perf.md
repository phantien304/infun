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

**Cập nhật 2026-07-22**: đã bỏ hẳn cách expose `tcp://2375` (Docker Desktop chỉ
bind `127.0.0.1` dù tick "expose without TLS", phải dùng `netsh portproxy` để
lộ ra LAN — không mã hoá, không xác thực, và checkbox "expose" bị Docker
Desktop tự reset OFF sau mỗi lần update khiến portproxy còn "mở cổng" nhưng
backend phía sau chết, connect được nhưng mọi request bị đóng ngay — EOF/empty
reply). Chuyển sang **docker context qua SSH** (OpenSSH Server có sẵn trên
Windows, cổng 22, xác thực bằng key):

```powershell
# Trên máy để bàn (192.168.1.11), PowerShell Admin, 1 lần:
Add-WindowsCapability -Online -Name "OpenSSH.Server~~~~0.0.1.0"
Start-Service sshd
Set-Service -Name sshd -StartupType Automatic
New-NetFirewallRule -Name "OpenSSH-Server-In-TCP" -DisplayName "OpenSSH Server (sshd)" `
    -Enabled True -Direction Inbound -Protocol TCP -Action Allow -LocalPort 22
# Nếu tài khoản đăng nhập thuộc nhóm Administrators (trường hợp ở đây: user
# ADMIN), OpenSSH BẮT BUỘC dùng %ProgramData%\ssh\administrators_authorized_keys
# thay vì ~/.ssh/authorized_keys, với ACL chỉ SYSTEM + Administrators — nhầm
# chỗ này là lỗi hay gặp nhất khi setup OpenSSH trên Windows.
```

```bash
# Trên máy dev (~/.ssh/config có alias "staging-win" trỏ 192.168.1.11:22,
# IdentityFile riêng id_ed25519_staging_win, User ADMIN):
docker context create staging-ssh --docker "host=ssh://staging-win"

# Build local rồi transfer thẳng (nhanh + ổn định hơn build qua remote context —
# BuildKit qua session dài dễ bị "CANCELED" ở mốc 60s khi đường truyền không ổn định):
docker compose -f docker-compose.staging.yml build infun-php
docker save infun-app:staging | docker --context staging-ssh load
docker --context staging-ssh compose -f docker-compose.staging.yml up -d --scale infun-php=3
```

DB: copy full (schema + data) từ MySQL dev sang MySQL staging remote qua pipe
2 context cùng lúc (không cần export ra file trung gian):

```bash
docker exec infun-mysql mariadb-dump -uroot -proot --single-transaction --quick infun \
  | docker --context staging-ssh exec -i infun-mysql-staging mariadb -uroot -proot infun
```

`docker save | docker load` qua context remote **đôi khi bị đứt kết nối giữa
chừng** (`wsasend: An existing connection was forcibly closed`) — không phải lỗi
logic, thử lại là qua. Luôn `docker --context staging-ssh images infun-app:staging`
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
cat k6/mixed-30k.js | docker --context staging-ssh run --rm -i \
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

## 8. TARGET giữa 300-1500 (2026-07-22) — ngưỡng nghẽn sớm hơn dự đoán nhiều

Test 3 mốc 600/900/1200 (DURATION=1m) lần đầu sau khi chuyển hạ tầng sang
`staging-ssh` context (mục 1):

| TARGET | Browse p95 | Add p95 | Order p95 | http_req_failed | order_error | Ghi chú |
|---|---|---|---|---|---|---|
| 600  | 84.6ms  | 52.3ms | 72.4ms | 16.79% | 0 | Toàn bộ fail là **timeout client 60s**, không phải 5xx |
| 900  | 94.9ms  | 54.1ms | 73.9ms | 17.88% | 0 | Cùng dạng |
| 1200 | 106.7ms | 55.6ms | 71.4ms | 18.90% | 1 | Lỗi 5xx thật đầu tiên xuất hiện |

Khác hẳn dự đoán ban đầu (queue chậm dần tới gần 1500 mới nghẽn, xem mục 7):
p95 latency vẫn rất thấp và ổn định ở MỌI mức — không phải "chậm dần", mà là
**~17-19% request bị treo cứng đúng 60s rồi mới timeout**, ngay từ TARGET=600.
Nghĩa là có 1 tài nguyên **cố định** bị bão hoà sớm, không tỉ lệ thuận theo VU.

### Root cause: `pm.max_children` bão hoà, không phải DB chậm

Log php-fpm cả 3 replica đều có:
```
WARNING: [pool www] server reached pm.max_children setting (25), consider raising it
```
75 worker tổng (25 × 3 replica) là trần cứng. Ban đầu nghi do query browse có
`filter[keyword]` chậm (2-9s theo slowlog `request_slowlog_timeout=2s`), nhưng
bật `slow_query_log` (`long_query_time=0.5`) rồi soi bằng `mysqldumpslow -s t`
cho thấy **KHÔNG có query nào chậm thật** — nặng nhất (COUNT quét 500k dòng
`date_available`) trung bình chỉ 0.08s. Nghĩa là 2-9s "chậm" ở php-fpm slowlog
là do **tranh CPU** (75-180 worker PHP-FPM cộng thêm MySQL×3 + ProxySQL cùng
chia nhau 12 lõi vật lý), không phải thiếu index — khác hẳn pattern lỗi
`date_available` ở mục 4 (đó là do thiếu index thật).

**Kết luận quan trọng: KHÔNG nên tiếp tục tăng `pm.max_children` quá cao** —
oversubscribe CPU chỉ tăng context-switching, không tăng throughput thật.
Hướng đúng để vượt ngưỡng này là scale ngang (thêm máy), đúng kết luận đã ghi
ở mục 7.

### Fix áp dụng — `docker/php/www.pool.conf`

- `pm.max_children`: 25 → **60** (tận dụng RAM/CPU dư — baseline idle CPU
  <3%, RAM dư hàng chục GB so với 15.5GB Docker cấp).
- `request_terminate_timeout`: 60s → **15s** — lý do chính khiến kết quả
  cải thiện rõ: 60s làm worker kẹt giữ chỗ quá lâu, hàng đợi dồn ứ cấp số
  nhân; 15s vẫn dư margin so với query chậm nhất đo được (~9s) nhưng giải
  phóng worker nhanh hơn nhiều khi kẹt thật.

Build lại + transfer (`docker save | docker --context staging-ssh load`) +
`up -d --scale infun-php=3` để nhận image mới.

### Kết quả sau fix (TARGET=900, so trực tiếp với dòng 900 ở bảng trên)

| | Trước fix | Sau fix |
|---|---|---|
| Max latency | 59.99s (chạm trần) | ~20-25s |
| Throughput (http_reqs/1.5m) | 33,279 | 45,087 (+35%) |
| checks_failed (browse/order/flash) | 0.75% | **0%** |
| http_req_failed | 17.88% | 15.25% |
| order_error | 0 | 0 |

Vẫn còn 15.25% "fail" — cần điều tra tiếp có bao nhiêu % trong đó là 422 hợp
lệ (chống oversell) vs timeout/lỗi thật (xem mục "nợ" — threshold quá chặt).

### Phát hiện phụ — dữ liệu test bị nhiễm bẩn qua nhiều lần chạy k6

7+ lượt chạy k6 liên tiếp trong ngày làm cạn stock của **variant mặc định**
(is_default) cho từng `PRODUCT_IDS` dùng để test — dù các variant KHÁC của
cùng sản phẩm vẫn còn hàng. Add-to-cart không truyền `variant_id` nên luôn
resolve về variant mặc định → 422 "không đủ số lượng trong kho" cho TOÀN BỘ
add, dù bảng `product_stock` tổng nhìn vẫn còn hàng (dễ nhầm là hệ thống lỗi).
Đã restock các variant mặc định của `3,6,9,14,17,19,21,27` lên `on_hand=5000`,
reset `730` (variant flash-sale cố ý khan hiếm) về `on_hand=1`.

**Bài học**: sau mỗi đợt k6, nên restock lại các sản phẩm test (không chỉ
truncate `orders` như đã note ở đầu file) — nếu không, lần chạy sau sẽ cho số
liệu sai lệch do hết hàng thật chứ không phải do hạ tầng.

### ⚠️ Cần điều tra riêng (chưa xử lý trong phiên này)

- `flash_ok` ở lần chạy TARGET=900 sau restock ra **1675** dù variant flash
  (`730`) chỉ có `on_hand=1` — con số này vô lý nếu đúng nghĩa "1675 đơn hàng
  thật mua thành công sản phẩm chỉ có 1 tồn kho". Nghi `checkoutCod()` vẫn trả
  2xx (tính vào `flash_ok`) ngay cả khi giỏ hàng KHÔNG có sản phẩm flash (do
  `postAdd(FLASH_PRODUCT_ID)` trước đó bị 422) — tức có thể tạo **đơn hàng
  rỗng** thay vì báo lỗi. Cần kiểm tra `orders_product` xem có đúng 1 order
  chứa variant 791 hay không, và review logic `save-order` có validate giỏ
  hàng rỗng chưa. KHÔNG kết luận đây là bug oversell thật — chỉ là nghi vấn
  cần verify riêng.

---

## 9. Tăng cache hit — cache query danh mục/phân trang + đo đúng steady-state (2026-07-22)

Xuất phát từ soi lại nguyên tắc 80/20 trong `k6/mixed-30k.js`: cơ chế Zipf có
đúng, nhưng hit thực tế bị chặn trần vì **`CachePage` BYPASS mọi URL có query
string** (trừ UTM) — tức trang danh mục / phân trang / sort (phần lớn traffic
browse thương mại điện tử) KHÔNG BAO GIỜ được cache. `k6` càng làm lộ vì
`listUrl()` luôn gắn `page=...` → 100% BYPASS.

### B — `CachePage` cache thêm whitelist query param

`app/Http/Middleware/CachePage.php` — thay "có query → BYPASS" bằng whitelist:

- Cache khi query CHỈ gồm `page` / `per_page` / `sort` / `filter[...]` với sub-key
  thuộc `{category_id, manufacturer_id, filter_value_id}`. Value phải hợp lệ
  (`page/per_page` là số, `sort` khớp `/^-?[a-z_]+$/i`, id là số/mảng số) — value
  rác → BYPASS an toàn, chặn cache poisoning/cache-busting.
- CỐ Ý loại (vẫn BYPASS): `filter[keyword]` (free-text → Meilisearch, cardinality
  vô hạn), `filter[price_min|max]` (range liên tục), `filter[in_stock]` (dễ stale).
- Cache key nhúng query đã CHUẨN HOÁ (sort key + sort value mảng) → 2 URL cùng
  nghĩa khác thứ tự dùng chung 1 entry. Key path-trần GIỮ NGUYÊN byte như cũ →
  không invalidate cache homepage/list hiện có.
- Chỉ chạy cho guest (`auth()->check()` return sớm) → giá theo user group mặc
  định, không lệch giá.

### Bật invalidation page cache

`app/Observers/CacheFlushObserver.php` — bỏ comment `CacheGate::flushPages()`.
Bắt buộc: giờ cache cả trang danh mục nên khi product/category đổi phải flush,
nếu không stale tới hết TTL 24h. Tag flush trên redis rẻ (bump version), no-op
trên file driver. Trade-off: 1 write CMS xoá toàn page cache — chấp nhận vì
đọc >> ghi. **Chỉ hiệu lực khi `config_redis_cache=1`** (file driver không có
tag → danh mục vẫn stale theo TTL; prod nên dùng redis).

### Track A — `k6/mixed-30k.js` đo đúng hit sau khi B bật

- **`setup()` warm-up**: production cache ấm 24/7, test 1 phút đang đo
  cold-start. `setup()` ấm trước `BROWSE_PATHS` + tổ hợp category hot (page 1 ±
  sort) trên page store (server-side, chia sẻ mọi VU). Tắt bằng `-e WARMUP=0`.
- **`listUrl()` viết lại**: đa số là browse danh mục CACHEABLE
  (`filter[category_id]` Zipf 80/20 + `page` lệch về 1 + đôi khi sort/per_page)
  → lặp trang hot → HIT. `KEYWORD_RATIO` (mặc định 0.25) phần đi
  `filter[keyword]` search (BYPASS, đo Meili). Env mới: `CATEGORY_IDS`
  (**mặc định `1-50` theo seed test**), `SORTS`, `KEYWORD_RATIO`, `WARMUP`.
- **Threshold `cache_hit` 0.5 → 0.8**: sau warm + category cacheable, steady-state
  kỳ vọng >0.9; ngưỡng cũ 0.5 quá lỏng để bắt regression.

Ước tính hit sau thay đổi: browse = 80% path-trần (HIT) + 20% listUrl, trong đó
~75% category (HIT) + ~25% keyword (BYPASS) → cacheable ≈ 95%.

### Chống cạn quantity qua nhiều lượt k6 — `k6:provision-stock`

`app/Console/Commands/K6ProvisionStockCommand.php` — giải quyết dứt điểm mục 8
"Phát hiện phụ" (default variant cạn dần → 422 giả). Chạy TRƯỚC mỗi đợt test:

```bash
php artisan k6:provision-stock                                  # default set
php artisan k6:provision-stock --products=3,6,9,14,17,19,21,27 --flash=730
php artisan k6:provision-stock --products=1-50 --stock=500000
```

- Product thường: `on_hand` default variant lên `--stock` (mặc định 1.000.000) +
  `reserved=0`, GIỮ policy `Deny` → vẫn test đúng đường reserve/deduct lock, chỉ
  không bao giờ cạn trong 1 đợt.
- Product flash: `on_hand=--flash-stock` (mặc định 1) → giữ khan hiếm cho oversell.
- Idempotent; ghi `stock_movement` type=adjust (delta) giữ bất biến
  `on_hand = SUM(movement)`; `--products` nhận list "1,2" hoặc range "1-50".

### Cần verify trên staging (chưa chạy được — sandbox không có php-cli)

- `php artisan config:clear` (đổi middleware) + smoke:
  `curl -sI 'http://.../san-pham?filter[category_id]=3&page=2'` → lần 1 `X-Cache:
  MISS`, lần 2 `HIT`; `?filter[keyword]=áo` phải `BYPASS`.
- Chạy `k6:provision-stock` rồi bắn lại `mixed-30k.js` đo `cache_hit` mới.

---

## Việc còn nợ / gợi ý bước tiếp

- [ ] Commit 3 fix app-level (mục 2-4) + 2 fix k6 script (mục 6) — đang nằm
      trong working tree, chưa commit.
- [ ] `http_req_failed: ['rate<0.02']` trong threshold của `mixed-30k.js` hơi
      quá chặt cho kịch bản có `flash_sale` (cố tình tạo tranh chấp tồn kho →
      422 là kết quả ĐÚNG, không phải lỗi) — cân nhắc tách riêng ngưỡng cho
      browse/add/order (không tính 422) thay vì 1 ngưỡng chung cho tất cả HTTP.
- [x] ~~Chưa test `TARGET` ở khoảng giữa 300-1500~~ — done 2026-07-22, xem mục 8.
      Ngưỡng nghẽn thật nằm ở 75 worker php-fpm (không phải DB), đã tăng lên
      180 (60×3) + hạ `request_terminate_timeout` 60s→15s. Vẫn còn 15.25%
      `http_req_failed` ở TARGET=900 sau fix — CHƯA xác định được bao nhiêu %
      là 422 hợp lệ vs lỗi thật (cần thêm 1 vòng đo có phân loại).
- [ ] Điều tra `flash_ok` bất thường (1675 "thành công" cho sản phẩm chỉ có
      1 tồn kho) — nghi `save-order` không validate giỏ hàng rỗng, xem chi
      tiết ở mục 8 "Cần điều tra riêng".
- [ ] Muốn tiến gần hơn tới kịch bản "30k active" thật cần k6 phân tán
      (k6-operator/Grafana Cloud) + nhiều app server thật, theo đúng
      `docs/SCALE-30K.md` mục D — máy đơn không đại diện cho hạ tầng multi-server.
      Mục 8 đã củng cố thêm bằng chứng: 1 máy 12 lõi bão hoà CPU ở ~75-180
      php-fpm worker, không phải do query chậm — scale ngang là hướng đúng
      duy nhất để vượt ngưỡng này, không phải tối ưu thêm code/index.
- [ ] `docker save | docker load` qua remote context thỉnh thoảng đứt kết nối
      giữa chừng — nếu làm quy trình deploy lặp lại thường xuyên, nên cân nhắc
      dùng registry riêng (vd 1 registry local) thay vì save/load tay.
- [x] ~~Restock định kỳ các sản phẩm dùng trong `k6/mixed-30k.js`~~ — done
      2026-07-22 (mục 9): command `k6:provision-stock` reset on_hand default
      variant idempotent, chạy trước mỗi đợt test. Có thể nối vào script bắn k6.
