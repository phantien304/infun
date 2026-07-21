# CHANGELOG 2026-07-21 — Page-cache user-agnostic + Sidebar fragment + List COUNT-free

> Bàn giao để **build lại staging + bắn k6**. Mục tiêu đợt này: làm HTML guest
> user-agnostic để bật lại `cache_page` an toàn, hạ chi phí mỗi request MISS
> (fragment + render() gầy), và **bỏ hẳn COUNT(\*)** ở trang list (điểm giết
> read-replica ở quy mô 500k / 30k active).

---

## 1. Tóm tắt thay đổi (theo nhóm)

### A. Header user-agnostic — điều kiện để `cache_page` / edge an toàn
- **Badge giỏ/wishlist → JS.** `CartBadgeController@index` (mới) trả `/cart/badge`
  JSON `no-store` (đọc session). Blade SSR để `0` + `data-cart-badge` /
  `data-wishlist-badge`; `share/menu.blade` hydrate client-side. Sửa bug cache
  dính số giỏ của guest khác.
- **Menu active-state → JS.** `MenusClient` bỏ `processMenuBeforeRender`
  (`preg_replace` mỗi request) → menu HTML **hằng số per-locale**; JS đánh dấu
  `active` theo `location.pathname`.
- **CSRF cho trang cache.** `share/menu.blade` fetch `/give-me-csrf` (route ngoài
  group cache → luôn tươi) ghi đè `<meta csrf-token>` + `input[_token]`; jQuery
  `ajaxPrefilter` gắn token mới nhất + `ajaxError` retry-1-lần khi 419;
  `style.js` (add-to-cart/consult-sign) nuốt dialog 419 để retry im lặng (không
  nháy lỗi). → guest POST chạy đúng dù trang bị cache.
- **Badge tự sync sau add/wishlist** (không F5): `ajaxComplete` re-hydrate từ
  session + sequence guard chống race.

### B. Sidebar fragment (facets + cây danh mục) + `render()` gầy
- `App\View\FragmentCache` (mới): `facets()` + `categoryTree()` — cache chuỗi
  HTML **tĩnh per-locale**, build-on-read-miss + invalidate qua observer (giống
  `getMenus`). Facets/tree bỏ mọi trạng thái động (checked/active/open) → JS
  hydrate từ query string.
- `Controller::render()` **không còn** `categories/manufacturers/filters/zones`
  → mọi trang non-listing (home, product detail, cart, checkout, blog, account)
  bớt 4 Redis GET + rehydrate hàng trăm model + 4 `DTO::collect` mỗi request.
- Invalidate: `Manufacturer/FilterRepository::flushCache → forgetFacets`;
  `CategoryRepository::flushCache → forgetTree`.
- Xoá dead blade: `page/child/menu_home_top.blade.php`,
  `page/child/manufacturer.blade.php`.

### C. Zones ra API (bỏ khỏi SSR)
- `ResourceController@zone`: `Cache-Control: public, max-age=86400` + `ETag` +
  `isNotModified` (304). Route `resource/zone` `withoutMiddleware(cache_page)`.
- Checkout + address form nạp select tỉnh/thành **client-side** từ `/resource/zone`
  (mẫu district/ward vốn đã có). `render()` không còn `zones`.

### D. List COUNT-free (500k)
- `ProductRepository`: nhánh DB của `list()` → `simpleDbList()` dùng
  `simplePaginate` → **KHÔNG chạy `COUNT(*)` bao giờ** (chỉ `LIMIT perPage+1`).
- Bỏ "Có X sản phẩm" + phân trang số; thay bằng **Trước/Sau** (không cần
  `total()`/`lastPage()`). `_paging` (blog/orders/review) giữ nguyên.
- Return type `list()`/`getListSpecial()` nới sang contract `Paginator` (an toàn,
  covariant). Nhóm cache-COUNT (roadmap #3) **giữ lại dạng dead code** để bật lại.

### E. `CachePage` 80/20
- Chỉ cache trang **sạch** (không query có nghĩa); có filter/sort/page/search →
  `BYPASS`. Bỏ allowlist param + `maxCacheablePage` + normalize query + device.
  Key = `pc:md5(path|locale|currency)` — cardinality chặn cứng ở số path tĩnh.
- Test `tests/Unit/CachePageKeyTest.php` viết lại theo contract mới.

### F. k6
- `k6/mixed-30k.js`: browse phân bố **Zipf 80/20** (`BROWSE_PATHS`) + metric
  `cache_hit` (assert `X-Cache: HIT`) + CSRF lấy từ `/give-me-csrf`.

### File đụng tới (để review nhanh)
```
app/Http/Controllers/Web/CartBadgeController.php      (mới)
app/Http/Controllers/Web/ResourceController.php
app/Http/Controllers/Controller.php
app/Http/Middleware/CachePage.php
app/Http/Supports/MenusClient.php
app/View/FragmentCache.php                            (mới)
app/Repositories/Eloquent/ProductRepository.php
app/Repositories/Eloquent/CategoryRepository.php
app/Repositories/Eloquent/ManufacturerRepository.php
app/Repositories/Eloquent/FilterRepository.php
app/Repositories/Base/QueryableRepository.php
app/Repositories/Interfaces/ProductRepositoryInterface.php
routes/web.php                                        (bật lại cache_page)
public/web/js/style.js                                (nuốt 419)
resources/web/views/share/menu.blade.php
resources/web/views/share/structure/_product_listing.blade.php
resources/web/views/category/structure/_side_bar.blade.php
resources/web/views/category/structure/_side_bar_node.blade.php
resources/web/views/category/structure/_side_bar_facets_static.blade.php   (mới)
resources/web/views/category/structure/_side_bar_tree_static.blade.php     (mới)
resources/web/views/checkout/_choose_address.blade.php
resources/web/views/checkout/index.blade.php
resources/web/views/account/address_form.blade.php
tests/Unit/CachePageKeyTest.php
k6/mixed-30k.js
XOÁ: resources/web/views/page/child/menu_home_top.blade.php
XOÁ: resources/web/views/page/child/manufacturer.blade.php
```

---

## 2. Build lại staging (image bất biến)

> Code nướng vào image + OPcache `validate_timestamps=0` → code mới CHỈ vào bằng
> build lại image rồi recreate. Chi tiết: `docs/STAGING.md`.

```bash
# shell mới thì set lại (image không kèm .env)
export STAGING_APP_KEY="base64:..."
export STAGING_APP_URL="http://localhost:8100"   # hoặc host staging thật

# build + recreate, GIỮ scale để test tải
docker compose -f docker-compose.staging.yml build infun-php
docker compose -f docker-compose.staging.yml up -d --scale infun-php=3
```

Entrypoint (`entrypoint.staging.sh`) **tự lo** khi container start:
- `optimize:clear` → `cache:clear` **flush Redis cache** (page `pc:*`, fragment
  `frag:*`, menu HTML, repo list) → không phục vụ HTML stale từ code cũ.
- `config:cache` + `route:cache` → nuốt `routes/web.php` (bật lại `cache_page`).
- `view:cache` → biên dịch lại toàn bộ blade đã sửa.

**KHÔNG cần** đợt này: `migrate` (không có migration mới) · `scout:import`
(index search không đổi).

> ⚠️ Nhớ kèm `--scale infun-php=3` — thiếu nó `up -d infun-php` reset về 1
> instance. Không bao giờ `down -v` (mất DB/Redis staging).

---

## 3. Checklist verify SAU khi build (trước khi bắn k6)

```bash
docker compose -f docker-compose.staging.yml ps                     # infun-php = 3, healthy
docker compose -f docker-compose.staging.yml logs --tail=40 infun-php # entrypoint sạch, không crash
```

Mở web (guest, ẩn danh):
- [ ] **Tailwind purge**: trang category — node đang xem **nền brand + chữ trắng**
      (class active giờ nằm trong chuỗi JS; nếu mất màu → safelist
      `bg-brand border-brand text-white font-semibold hover:text-white` rồi build lại).
- [ ] **X-Cache**: trang sạch (`/`, `/danh-muc-cxx`, `/sp-pxxx`) → lần 1 `MISS`,
      lần 2 `HIT`. Thêm `?page=2` / `?filter[...]` → `BYPASS`.
- [ ] **Badge**: add-to-cart (trang detail & listing) → số giỏ nhảy ngay, **không
      F5**; PC + mobile đều đổi. Trang đang `X-Cache: HIT` add-to-cart **không 419**.
- [ ] **Wishlist** (user đăng nhập): toggle → badge wishlist sync ngay.
- [ ] **Zones**: checkout guest → dropdown tỉnh nạp từ `/resource/zone`
      (Network: `Cache-Control: public, max-age=86400` + `ETag`, lần 2 `304`);
      chọn tỉnh → quận → phường cascade; `zone_name` hidden có giá trị.
- [ ] **List COUNT-free**: bật slow query log, mở trang list → **không còn**
      `COUNT(*)`, chỉ `SELECT ... LIMIT`; UI phân trang **Trước/Sau**.
- [ ] `php artisan test --filter=CachePageKeyTest` (trong container) → xanh.

---

## 4. Bắn k6 phân tán 3k → 10k → 30k

> Nền: `docs/SCALE-30K.md` mục 7 + `k6/DISTRIBUTED.md`. 1 máy KHÔNG kéo nổi 30k
> VU → N máy sạch, mỗi máy `TARGET = 30000/N`. Chạy từ máy sạch (KHÔNG phải máy
> dev đang gánh nhiều thứ — bài học mục 5).

### Chuẩn bị (một lần)
```bash
# nới throttle (không thì chỉ đo 429), mail=log, payment cod — set trong ENV staging
THROTTLE_ADD_TO_CART=100000
THROTTLE_SAVE_ORDER=100000
# seed SP flash tồn nhỏ (xem DISTRIBUTED.md §1)
```
`BROWSE_PATHS` đặt **slug thật** để Zipf tạo HIT:
```
-e BROWSE_PATHS="/,/san-pham,/<category-slug-cxx>,/<product-slug-pxxx>,/khuyen-mai"
```

### Leo nấc (giữ mỗi nấc vài phút steady-state, KHÔNG spike)
```bash
# N = số máy load-gen; TARGET mỗi máy = nấc / N
k6 run -e BASE_URL=http://staging -e TARGET=<3000|10000|30000>/N \
       -e BROWSE_PATHS="..." -e PRODUCT_IDS=... -e FLASH_PRODUCT_ID=<id> \
       -e ZONE_ID=... -e DISTRICT_ID=... -e WARD_ID=... k6/mixed-30k.js
```

| Nấc | Mục đích | Đỏ ở đây nghĩa là |
|---|---|---|
| **3k** | Smoke — topology đấu đúng (LB/replica/ProxySQL/Redis/cache_page/gate) | **Lỗi cấu hình**, không phải capacity |
| **10k** | Ép — tier nào bão hoà trước | Ghi p95 đi kèm CPU hay replica-lag |
| **30k** | Đích — chịu mô hình traffic, không oversell/5xx | Điểm gãy thật |

### Mỗi nấc soi 3 tín hiệu (roadmap)
```bash
# HIT ratio: metric cache_hit trong output k6 + đếm HIT
# CPU app: busy php-fpm workers / CPU%  (fpm-status nếu bật)
# Replica lag:
mysql -h <replica> -e "SHOW SLAVE STATUS\G" | grep Seconds_Behind_Master
# DB master lock (flash/write):
mysql -h <master> -e "SHOW PROCESSLIST" | grep -c "Waiting for"
# Flash gate:
docker compose -f docker-compose.staging.yml exec infun-php php artisan flash-gate:status <variant>
```

Đọc kết quả → scale đúng tier:
- p95↑ + **app CPU pegged** → thêm app server (ngang, rẻ).
- p95↑ + **replica lag phình** → thêm replica / cắt read (COUNT-free đã gỡ read
  killer lớn nhất → lag phải thấp; nếu vẫn phình, soi slow log replica).
- 5xx flash + **lock wait master** → tranh chấp GHI, replica không cứu → đòn bẩy
  là Redis admission gate.
- **HIT ratio tụt** khi VU↑ → cache/edge, không phải DB.

### Ngưỡng đạt (thresholds trong script)
- 5xx = 0 (flash hết hàng phải là 422 sạch).
- browse p95 < 800ms (cache ấm) · add-to-cart p95 < 2s dưới đỉnh tranh chấp.
- Không oversell (`k6/verify-oversell.sql`) · gate không leak.
- Replica lag đỉnh < 10s.

> Đo worst-case (cache lạnh): flush trước khi bắn —
> `php artisan tinker --execute="\App\Helpers\CacheGate::flushAll();"`.

---

## 5. Rollback nhanh
- **Tắt cache_page**: bỏ `'cache_page'` khỏi group middleware trong `routes/web.php`.
- **Quay lại phân trang số + cache COUNT**: trong `ProductRepository::list`, trỏ
  `simpleDbList` → `listDbCachedCount` (đang giữ), và bỏ comment 2 khối trong
  `_product_listing.blade.php`.
- **Còn treo (chưa làm, cần domain/hạ tầng)**: Cloudflare Cache Everything (edge),
  scale ngang 7–8 node, Meilisearch instance riêng cho browse không-từ-khoá.
```
