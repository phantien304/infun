# Trang list sản phẩm: Page cache & hướng search-first (Shopee-style)

> Ghi chú kỹ thuật để cân nhắc. Bối cảnh: trang list sản phẩm render nặng.
> Stack hiện tại đã có **Laravel Scout + Meilisearch** nhưng list browse vẫn
> chạy SQL. Tài liệu này gộp: rà soát `CachePage`, đánh giá bỏ hẳn page cache,
> hướng kiến trúc kiểu marketplace, và spec field cần index.

---

## 1. Rà soát `app/Http/Middleware/CachePage.php` (bản gốc)

Vấn đề phát hiện:

1. **`minifyHtml` bằng `preg_replace` toàn trang**
   - Chỉ chạy khi cache MISS (không phải mỗi request) → không phải nút thắt tốc độ.
   - Nhưng gom cả whitespace trong `<pre>/<textarea>/<script>/<style>` (nơi khoảng
     trắng có nghĩa) → rủi ro đổi nội dung.
   - Không guard `null`: trang quá lớn có thể chạm `pcre.backtrack_limit` →
     `preg_replace` trả `null` → **cache trang trắng 24h**.
   - Lợi ích ~1-3%, thua xa gzip/brotli ở web server/CDN.

2. **Cache key theo URL + query (blacklist tracking)**
   - Chỉ loại `utm_*`, `aff`, `ref`, `gclid`, `fbclid`. Mọi param khác vẫn tạo key mới.
   - `?q=`/`?search=` (tự do) = **cardinality vô hạn**; faceted filter = bùng nổ tổ hợp,
     nhân thêm `device(2) × locale × currency × page × sort`.
   - Trang search cũng bị cache kèm query → chính là chỗ dễ tràn.

3. **Rủi ro RAM / đĩa**
   - Redis không set `maxmemory` + policy → flood tới OOM. Có policy thì thrash,
     đá luôn cache app (vì `pageStore` và `store` chung `GLOBAL_TAG`).
   - File store: phình đĩa/inode, chậm dần.

4. **CSRF trong HTML cache**: chỉ cache guest (tốt). App đã có route `/give-me-csrf`
   (lấy CSRF qua JS) → footgun này gần như đã xử lý.

---

## 2. Bản đã sửa (ĐÃ APPLY): allowlist + bỏ minify

File: `app/Http/Middleware/CachePage.php` + test `tests/Unit/CachePageKeyTest.php`.

- **Allowlist param** vào key: `page, sort, order, filter, rating, in_stock, tag, brand, manufacturer`.
- Param ngoài allowlist (đã trừ tracking) → **BYPASS** (đi thẳng, không cache) —
  tránh gộp nhầm key + nổ cardinality.
- `page` phải là số & `<= 50`, ngoài ngưỡng → BYPASS (chặn `?page=1..∞`).
- **Bỏ hẳn `minifyHtml`/`preg_replace`** — cache HTML gốc.
- Header debug: `X-Cache: HIT | MISS | BYPASS`.

> Đây là bản "hardened" để làm origin shield tạm thời. Vẫn còn nhiều bước check —
> lý do cân nhắc hướng tốt hơn ở mục 4-6.

---

## 3. Đánh giá phương án BỎ HẲN page cache

- App-level page cache chạy ở **middleware — sau khi Laravel bootstrap**. Kể cả HIT
  vẫn trả tiền bootstrap framework; chỉ tiết kiệm render + query.
- **Bỏ hẳn hợp lý khi**: chuyển full-page cache lên **Cloudflare/Nginx** (phục vụ
  TRƯỚC khi chạm PHP), hoặc trang render nhẹ + traffic vừa.
- **Không nên bỏ khi**: trang list render NẶNG (đúng trường hợp hiện tại) và chưa có
  edge cache → mọi guest render thật → spike DB/CPU.
- Khác biệt quan trọng: **app cache biết `auth()->check()`** (chỉ cache guest);
  **edge cache không biết login** → phải cấu hình **bypass khi có cookie session**.
- Khuyến nghị trung dung: giữ app-cache hardened **+** Cloudflare Cache Rule cho path
  công khai (Cache Everything + Edge TTL + bỏ tracking param + bypass cookie session).
  Chỉ bỏ hẳn app-cache **sau khi** đo edge hit ratio ổn định.

---

## 4. Marketplace lớn (Shopee/Lazada/Amazon) làm gì

Pattern chung — **không cache HTML render per-URL**:

1. **API-first**: trang list = app shell tĩnh (giống nhau cho mọi người) + data qua JSON API.
2. **Browse/search chạy trên search engine** (read-model denormalize sẵn), không đụng
   DB giao dịch. Giá/kho/discount đẩy **bất đồng bộ** vào index.
3. **Cache DATA (JSON), không cache HTML** — payload nhỏ (KB), tái dùng cho mọi
   device/locale, anonymous cache ở CDN/edge TTL ngắn.
4. **Phân trang cursor/keyset** (`search_after`) → không có "trang 50", không tốn khi trang sâu.
5. **Personalization tách riêng** (giỏ hàng, wishlist, gợi ý) ghép ở client → HTML dùng chung được.
6. **Cache key = query chuẩn hoá** (category + facet từ taxonomy cố định + sort + cursor)
   → bounded, không nổ như cache theo URL tự do.

Cốt lõi: cache cái **nhẹ & ổn định (data)**; đẩy phần nặng (lọc/sắp/phân trang) xuống
engine; HTML giống nhau để CDN lo.

---

## 5. Áp vào codebase (tăng dần, KHÔNG viết lại)

**Trạng thái hiện tại:** `toSearchableArray` đã index nhiều field thẻ, nhưng
`ProductRepository::list()` **chỉ dùng Meilisearch khi có `filter.keyword`**; browse
không từ khoá rơi về SQL (`parent::list()`) → đây là path nặng.

### Bước 1 — Cho browse đi qua Meilisearch (thắng lớn nhất)
- Bỏ điều kiện "chỉ Meili khi có keyword"; cho `searchViaMeilisearch` chạy cả khi
  keyword rỗng (`Product::search('')` = match-all + filter/sort/paginate).
- Khai `filterableAttributes` + `sortableAttributes` (mục 6).
- List đọc từ index (~1-5ms) thay vì SQL join nặng → **cache full-page HTML gần như
  không cần**, hết luôn màn "check nhiều bước + giới hạn 50 trang".
- *Zero-DB đúng kiểu Shopee*: build `ProductDTO` thẳng từ payload Meili (`->raw()`),
  vì `paginate()` mặc định của Scout vẫn query DB `WHERE id IN (...)` để hydrate.

### Bước 2 — Cache DATA thay vì HTML
- Cache collection DTO (JSON) theo filter chuẩn hoá, TTL ngắn (60-300s).
- Có thể expose endpoint JSON, hydrate thẻ ở client → HTML shell giống nhau → CDN cache dễ.

### Bước 3 (tuỳ chọn, full model)
- Shell tĩnh trên CDN + list render client từ JSON API + keyset pagination +
  personalization client-side → bỏ hẳn cache HTML per-URL.

---

## 6. Spec field Meilisearch cho thẻ list

Đối chiếu card `resources/web/views/product/structure/_product.blade.php` + `ProductDTO`.

### 6.1 Đã có trong index (giữ)
`id, sku, model, name, description, manufacturer_id, sort_order, has_variants,
min_variant_price, max_variant_price, max_variant_discount_percent, viewed,
rating_avg, created_at, category_id[], filter_value_id[]`

### 6.2 THIẾU — card render nhưng index chưa có (phải THÊM)

| Field thêm | Card dùng để | Nguồn |
|---|---|---|
| `slug` | `$product->url` (buildUrl slug+id) | description.slug/name |
| `image` | `thumbnail(300,300)` | product.image |
| `badge` | badge góc ảnh | product.badge |
| `review_count` | số đánh giá | product.review_count |
| `weight`, `weight_unit` | dòng khối lượng | product.weight + weightClass |
| `manufacturer_name`, `manufacturer_slug`, `manufacturer_image` | logo/tên/link NSX | manufacturer |
| `categories[] {id,name,slug}` | list category trên card | productCategories |
| `special_active`(bool), `special_price`, `special_regular_price`, `special_discount_percent`, `special_ends_at` | khối giá KM (simple): giá KM + giá gạch + `-X%` + countdown | defaultVariant.productVariantSpecial + regular_price |
| `price` | `priceLabel` khi KHÔNG có special | defaultVariant.price |

> SP **biến thể** đã đủ (`min/max_variant_price` + `max_variant_discount_percent` — đều
> là giá **hiệu lực**). Chỉ **SP đơn giản** cần khối `special_*` + `regular_price`.

### 6.3 THÊM cho FILTER / SORT (facets, không phải hiển thị)
- **filterableAttributes**: `category_id, manufacturer_id, filter_value_id, has_variants, in_stock, min_variant_price, rating_avg`
- **sortableAttributes**: `min_variant_price, created_at, viewed, rating_avg, sort_order, max_variant_discount_percent`
- ⇒ phải thêm **`in_stock` (bool)** vào index (hiện chưa có).

---

## 7. Ba điểm phải xử lý riêng (không chỉ thêm field)

1. **`matchedFilterNames`** — phụ thuộc query (filter đang chọn ∩ filter của SP).
   KHÔNG index tên per-product. Giữ `filter_value_id[]`, resolve tên qua **map
   `filter_value_id → name` cache sẵn** (nhỏ, theo locale) lúc render.

2. **Giá hiệu lực & special theo thời gian** — special bật/tắt theo `date_start/date_end`.
   Index là snapshot ⇒ re-index khi: (a) sửa giá/variant (đã có `searchableAllLocales()`
   trong `ProductWriteService`), **và (b) khi campaign special tới giờ bắt đầu/kết thúc**
   → cần cron/scheduled reindex (observer hiện chỉ chạy khi save, không tự chạy theo giờ).

3. **`in_stock` dễ lệch** — kho đổi mỗi đơn. Muốn lọc "còn hàng" chuẩn phải re-index khi
   trừ kho. Kiểu Shopee: list **gần đúng**, chốt chính xác ở PDP/add-to-cart (đã có
   reservation). Nên index `in_stock` gần đúng + re-index định kỳ, đừng ép realtime.

---

## 8. Việc tiếp theo (checklist quyết định)

- [ ] Đo trước khi quyết: HIT/MISS/BYPASS ratio (`X-Cache`) + thời gian render list.
- [ ] Bước 1: sửa `ProductRepository::list()`/`searchViaMeilisearch` cho browse không-từ-khoá.
- [ ] Cập nhật `Product::toSearchableArray()` theo mục 6.2 + eager-load trong
      `makeAllSearchableUsing` (tránh N+1 khi index).
- [ ] Khai `filterable/sortable` (mục 6.3) + chạy `scout:sync-index-settings`.
- [ ] (Tuỳ chọn) build `ProductDTO` từ hit Meili để list zero-DB.
- [ ] Hook re-index: special-expiry (cron) + stock-change.
- [ ] Sau khi list nhẹ: cân nhắc bỏ/giảm vai trò `CachePage`, hoặc chuyển full-page
      cache lên Cloudflare (bypass cookie session).

---

*Tài liệu tạo tự động để cân nhắc — chưa phải quyết định cuối. Các thay đổi code đã
áp: `CachePage.php` (allowlist + bỏ minify) và test. Phần Meilisearch/browse CHƯA áp.*
