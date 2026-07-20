# Audit cache entity load toàn hệ thống web — infun (2026-07-20)

> Câu hỏi: những entity nào đang được load cho MỌI trang web, trang nào thật sự
> dùng, và loại trừ thế nào để bớt Redis GET + unserialize + rehydrate + DTO
> mỗi request.

---

## 1. Cơ chế hiện tại

Điểm bơm dữ liệu global duy nhất: **`Controller::render()`** (base của mọi
controller web SSR). Mỗi lần render bất kỳ trang nào:

```php
'categories'    => CategoryDTO::collect($this->categoryRepo->listAllCached()),
'manufacturers' => ManufacturerDTO::collect($this->manufacturerRepo->listAllCached()),
'filters'       => FilterDTO::collect($this->filterRepo->listAllCached()),
'zones'         => ZoneDTO::collect($this->zoneRepo->listAllCached()),
'menus'         => $this->getMenus(),                    // HTML pre-rendered, cache riêng
'currencies'    => $this->currencyService->allCurrency(),
'languages'     => $this->languageRepo->listAllCached(),
```

`listAllCached()` = `rememberSystemModels()` (CacheableRepository): Redis GET →
unserialize mảng thuần → **rehydrate N model Eloquent** (`newFromBuilder`) →
`DTO::collect`. Đã tối ưu hơn serialize object graph, nhưng vẫn là chi phí CPU
**mỗi request × mỗi entity**, kể cả khi trang không dùng.

Ngoài base còn: `SetLocale` middleware (languages — mọi request web),
`CurrencyService` (currencies — memo per-request), Blog/StoreReview controllers
thêm 4 list nữa qua `buildDataCommon()` (đúng phạm vi trang của chúng).

Lưu ý: `cache_page` (mục 5 SCALE-30K) HIT thì bỏ qua toàn bộ `render()` — nhưng
chỉ cho **guest + GET + trang không except**. Checkout/account (nơi load `zones`)
nằm trong except-list, user đăng nhập không bao giờ HIT → chi phí này vẫn nguyên
với đúng những chỗ nóng nhất.

## 2. Mức độ sử dụng thật (đối chiếu blade)

| Data (mỗi render) | Blade dùng | Trang thật sự cần | Kết luận |
|---|---|---|---|
| `menus` | `share/menu` ← cả 2 layout | **mọi trang** | ✅ Giữ ở base. Đã cache HTML per-locale — tốt. |
| `currencies`, `languages`, `currentCurrency`, `currencySymbol` | `share/_locale_switcher` (trong menu) | **mọi trang** | ✅ Giữ ở base. Payload nhỏ (vài row). |
| `categories` | `category/structure/_side_bar` | Trang listing SP (product/category/manufacturer list, special) | ⚠️ Thừa trên: home, product detail, checkout, account, blog, cart… |
| `manufacturers` | `category/structure/_side_bar` (+ `page/child/manufacturer` — **DEAD**, không được include) | Trang listing SP | ⚠️ Như trên |
| `filters` (+`filterValues.description` — **payload lớn nhất**) | `category/structure/_side_bar` | Trang listing SP | ⚠️ Như trên — nặng nhất mà phạm vi hẹp nhất |
| `zones` (63 tỉnh + description) | `checkout/index`, `checkout/_choose_address`, `account/address_form` | Checkout + account address | ⚠️ Thừa trên mọi trang còn lại |

Blade chết (theme leftover, `page/home` chỉ include banner/about_us/store_review/
product_feature/blog_latest): `page/child/menu_home_top.blade.php` (dùng
`$categories`), `page/child/manufacturer.blade.php` (dùng `$manufacturers`),
và `categories/featured/flash_sale/newsletter/partners/why_choose.blade.php`
không được include ở đâu.

**Tổng thiệt hại mỗi request SSR không phải listing:** 4 Redis GET thừa
(categories, manufacturers, filters, zones) + rehydrate hàng trăm model +
4 lần `DTO::collect` — nhân với mọi request cache-MISS/user đăng nhập ở 30k
active là con số đáng kể, đặc biệt **checkout** (trang nóng nhất khi flash-sale
lại đang trả thêm categories/manufacturers/filters mà nó không render).

Đếm row thật để định cỡ (chạy trên DB):

```sql
SELECT 'category' t, COUNT(*) FROM category
UNION ALL SELECT 'manufacturer', COUNT(*) FROM manufacturer
UNION ALL SELECT 'filter', COUNT(*) FROM filter
UNION ALL SELECT 'filter_value', COUNT(*) FROM filter_value
UNION ALL SELECT 'zone', COUNT(*) FROM zone;
```

## 3. Kế hoạch loại trừ

### Phase A — Scope theo trang bằng View Composer (không đổi UI, ít xâm lấn)

1. Tạo `app/View/Composers/ProductSidebarComposer.php`: bơm
   `categories/manufacturers/filters` (đúng 3 dòng DTO::collect hiện tại).
   Đăng ký trong `AppServiceProvider::boot`:

   ```php
   View::composer('web::category.structure._side_bar', ProductSidebarComposer::class);
   ```

   Composer chỉ chạy KHI partial đó thật sự render → trang listing tự có data,
   mọi trang khác không tốn gì. Không phải sửa controller nào.
2. Tạo `ZonesComposer` gắn vào `web::checkout.index`,
   `web::checkout._choose_address`, `web::account.address_form`.
3. Gỡ 4 dòng `categories/manufacturers/filters/zones` khỏi `Controller::render()`.
   Giữ `menus/currencies/languages/currentCurrency` (mọi trang dùng thật).
4. Rà biến rơi rớt: `grep -rn '\$zones\|\$filters\|\$manufacturers\|\$categories'`
   trong `resources/web/views` sau khi gỡ — chỗ nào ngoài các blade ở bảng trên
   mà còn đọc biến (kể cả qua `$__data`) phải gắn thêm composer tương ứng.

### Phase B — Dọn dead code

5. Xoá (qua git, khôi phục được): `page/child/menu_home_top.blade.php`,
   `page/child/manufacturer.blade.php`; xác nhận với team rồi xử lý nốt
   `categories/featured/flash_sale/newsletter/partners/why_choose.blade.php`
   (nếu là tính năng treo thì giữ nhưng ghi chú — chúng không tốn runtime vì
   không được include, chỉ gây nhiễu audit).

### Phase C — Giảm chi phí phần còn lại (tùy chọn, sau khi đo)

6. **Cache SAU DTO** cho list ổn định: hiện tại rehydrate model → DTO mỗi
   request; với categories/filters có thể cache thẳng mảng DTO-ready
   (per-locale) → bỏ hẳn bước rehydrate + collect. Đổi ít: thêm
   `rememberSystemDtos()` trong `CacheableRepository`, flush giữ nguyên.
7. `filters` nếu vẫn nặng: cân nhắc cache fragment data theo
   locale (KHÔNG cache HTML sidebar — trạng thái checked phụ thuộc query string).
8. Shop chỉ 1 ngôn ngữ / 1 tiền tệ thật sự: config tắt switcher → bỏ luôn 2
   list languages/currencies khỏi base (SetLocale vẫn cần languages — giữ).

### Phase D — Đo trước/sau

```bash
# Đếm lệnh Redis 1 request (trước vs sau Phase A):
redis-cli MONITOR > /tmp/mon.txt & sleep 1; curl -s http://staging/<trang-detail> >/dev/null; sleep 1; kill %1
grep -c GET /tmp/mon.txt

# k6 A/B: k6/product-list.js (listing — kỳ vọng ~không đổi) và 1 trang
# product detail / checkout (kỳ vọng p95 giảm rõ). Đừng quên nới throttle.
```

**Định nghĩa xong:** trang non-listing không còn GET key categories/
manufacturers/filters/zones (soi MONITOR); k6 checkout p95 cải thiện;
sidebar/checkout/address hiển thị đúng như cũ; CMS sửa category/filter vẫn
thấy fresh (CacheFlushObserver không đổi — composer vẫn đọc qua
`listAllCached()`).

## 3b. PHƯƠNG ÁN B — TRIỆT ĐỂ (materialize-on-write + edge cache)

> Phương án A (composer) chỉ *di chuyển* chi phí về đúng trang cần. Phương án B
> *xoá* chi phí khỏi request path: HTML build sẵn LÚC GHI (content đổi ~vài
> lần/ngày) thay vì dựng lại LÚC ĐỌC (30k lần/phút). Request chỉ còn echo string.

### Tầng 0 — Đẩy guest traffic ra edge (Cloudflare Cache Everything)

Đã có sẵn zone Cloudflare (R2/CDN). Cache rule: **Cache Everything cho GET
guest, Bypass khi có cookie đăng nhập/giỏ hàng**; TTL ngắn + purge API khi
content đổi (móc vào `CacheFlushObserver` — chỗ đang gọi `flushPages()` gọi
thêm CF purge, debounce 30-60s). Kết quả: browse của 30k active phần lớn dừng
ở PoP, origin chỉ còn MISS + user login + POST.

**Điều kiện bắt buộc — trang guest phải user-agnostic.** Hiện có 3 chỗ per-user
đang SSR trong layout, phải chuyển client-side:

1. **Badge giỏ hàng** `session('total_cart_header')` trong `share/menu` — ⚠️ đây
   còn là BUG SẴN CÓ với `cache_page` (mục 5): guest A bỏ hàng vào giỏ, trang bị
   cache → guest B thấy số giỏ của A. Sửa: badge + wishlist count hydrate bằng
   JS từ 1 endpoint JSON nhỏ (`/cart/badge`, no-store).
2. **CSRF token** trong meta — token theo session, cache chung sẽ 419 khi POST.
   Đã có sẵn `/give-me-csrf`: JS fetch token trước lần POST đầu (k6 script đang
   làm đúng cách này rồi).
3. **Menu active-state**: `MenusClient::processMenuBeforeRender()` đang
   `preg_replace` đánh dấu `class="active"` theo `request()->path()` TRÊN MỖI
   REQUEST — vừa tốn CPU vừa làm HTML phụ thuộc URL. Bỏ hẳn: 3 dòng JS so
   `location.pathname` với `data-link` lúc load → menu HTML thành HẰNG SỐ
   per-locale, cache được ở mọi tầng.

### Tầng 1 — Materialize-on-write các fragment dùng chung

Tạo `App\Services\View\FragmentCache` + builder chạy KHI GHI (observer/queue
job, không phải khi đọc):

| Fragment | Build khi | Key | Thay cho |
|---|---|---|---|
| Header menu (PC+mobile) | Menu/MenuValue saved | `frag:menu:{locale}` | getMenus + preg_replace mỗi request |
| Sidebar listing (cây category + manufacturers + filters, phần TĨNH) | Category/Manufacturer/Filter/FilterValue saved | `frag:sidebar:{locale}` | 3 entity list + DTO + build cây 283 dòng blade mỗi request |
| Locale/currency switcher | Language/Currency saved | `frag:switcher:{locale}:{currency}` | 2 entity list mỗi request |

Blade chỉ còn `{!! FragmentCache::get('sidebar') !!}`. Trạng thái động của
sidebar (checkbox checked, nhánh expand, min/max giá) do **JS đọc query string**
— UI không đổi, chỉ nơi set state đổi (server → client).

Build-on-write đồng thời **xoá stampede**: observer build bản mới rồi swap key
(không còn khoảnh khắc "cache trống, N request cùng dựng lại" như
remember-on-read sau flush).

### Tầng 2 — Zones và mọi data "chỉ-một-trang" ra API

`/resource/zone` ĐÃ TỒN TẠI và đã đọc từ cache. Checkout + address form chuyển
sang fetch JSON đó (kèm `Cache-Control: public, max-age=86400` + ETag → trình
duyệt/CDN tự cache, user chỉ tải 1 lần) → gỡ `zones` khỏi SSR hoàn toàn.
District/ward vốn đã AJAX theo mẫu này.

### Tầng 3 — render() gầy + dọn

`Controller::render()` chỉ còn: breadcrumbs, SEO/meta, currency hiện tại (memo).
KHÔNG entity list, KHÔNG DTO::collect nào ở base. DTO layer chỉ phục vụ JSON/
API path. Xoá dead blades (mục Phase B ở trên). `$cacheMap`/flush pipeline giữ
nguyên vai trò — nhưng handler đổi từ "forget" sang "rebuild fragment + purge CF".

### So sánh A vs B

| | A — Composer | B — Triệt để |
|---|---|---|
| Chi phí request non-listing | bỏ ~4 GET+DTO | bỏ ~4 GET+DTO |
| Chi phí request listing | GIỮ NGUYÊN (vẫn 3 list + DTO + build cây) | còn 1 GET string |
| Guest traffic chạm origin | 100% (trừ cache_page HIT) | ~5-10% (edge) |
| Bug badge giỏ dính cache | còn nguyên | sửa tận gốc |
| Đổi hành vi UI | không | JS active-state/badge (nháy nhẹ nếu làm ẩu — làm inline script trước paint thì không) |
| Effort | ~0.5 ngày | ~3-5 ngày (tầng 1-3) + cấu hình CF (tầng 0) |
| Rollback | dễ | từng tầng độc lập, rollback theo tầng |

### Thứ tự triển khai khuyến nghị (mỗi bước độc lập, ship riêng được)

1. Badge giỏ/wishlist → JS (sửa bug cache_page ngay cả khi dừng ở đây).
2. Menu: bỏ preg_replace active → JS; menu thành hằng số per-locale.
3. Sidebar fragment build-on-write + JS state (nặng nhất, lợi nhất).
4. Zones → dùng `/resource/zone` + HTTP cache; gỡ khỏi render().
5. Gỡ toàn bộ entity list khỏi `render()`; xoá dead blades.
6. Cloudflare Cache Everything + purge hook (sau khi 1-5 xong, trang guest đã
   user-agnostic).
7. Đo: `redis-cli MONITOR` per-request (đích: non-listing 1-2 GET, listing 2-3
   GET), k6 mixed-30k A/B, tỷ lệ cache HIT trên CF dashboard.

## 4. Rủi ro & lưu ý

- Composer per-partial chạy MỖI lần partial render — `_side_bar` chỉ include 1
  lần/trang nên không sao; nếu sau này include lặp trong vòng lặp thì memo
  trong composer (static/instance) để không GET Redis N lần.
- `checkout/_choose_address` được include từ `checkout/index`: gắn composer cả
  2 view (đã ghi ở bước 2) để dù include kiểu nào cũng có `$zones`.
- KHÔNG đụng cơ chế flush: `$cacheMap` trong `AppServiceProvider` +
  `CacheFlushObserver` (giờ kiêm `flushPages()` — mục 5) giữ nguyên.
- Blog/StoreReview `buildDataCommon()` load blogCategories/blogTags/products/
  storeReviews — ĐÚNG phạm vi trang của chúng, không nằm trong diện loại trừ;
  chỉ lưu ý `getProductLatest()` chưa cache (query mỗi request) — ứng viên
  cache riêng nếu muốn (TTL ngắn).
