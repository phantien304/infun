# CLAUDE.md — Dự án `infun`

Ghi chú quy ước kiến trúc cho Claude. Đọc file này trước khi sửa code.

## Tổng quan

Website thương mại điện tử (Laravel). **Đang refactor mạnh** — tồn tại song song
code cũ và code mới, đừng nhầm lẫn hai bên (xem mục "Cũ vs Mới").

## Kiến trúc 3 tầng

- **Controller** — `app/Http/Controllers/`. Base mới là `App\Http\Controllers\Controller`
  (abstract). Base dùng `lazyMap()` + `__get()` để resolve repository lười (lazy)
  qua container — không cần inject thủ công ở constructor từng controller.
  Mỗi controller set dữ liệu view trong `buildDataCommon()`; `render()` tự gọi nó.
- **Repository** — `app/Repositories/Eloquent/*` extends `QueryableRepository`,
  implement interface tương ứng ở `app/Repositories/Interfaces/*`.
- **Model** — `app/Models/Entities/*` extends `App\Models\Base\Base`.

## Caching — quy ước quan trọng

- Cache **chỉ sống ở tầng Repository**, qua trait `App\Repositories\Concerns\CacheableRepository`.
  KHÔNG đặt logic cache trong controller/service.
- API trait (consolidated 2026-06-11):
  - `rememberCache($key, $resolver, $ttl = null, $perLocale = true, array $tags = [])` —
    bread-and-butter. Tags optional; pass khi row thuộc cluster cần flush group.
    Driver hỗ trợ tag (redis, memcached) cache theo tag → flush được cả nhóm;
    driver khác (file, database) tự fallback về untagged remember — không gãy.
    Trước đây có 2 method riêng (`rememberCache` + `rememberCacheTagged`), đã
    gộp về 1 entry point này. Old `rememberCacheTagged` callers chỉ cần
    reorder: `$tags` chuyển xuống cuối thành named arg.
  - `forgetCache($key, $perLocale = true)` — flush 1 exact key, mọi locale.
    Dùng khi không có tag nào hợp lý; otherwise prefer `forgetCacheTagged`.
  - `forgetCacheTagged(array $tags)` — flush cluster invalidation. No-op trên
    file/database driver.
  - `rememberEntity($entity, $prefix, $resolver, ...)` — high-level helper key
    cache theo `$entity->getKeyAsString()` (single-PK lẫn composite-PK). Auto-
    resolve `$perLocale` theo PK shape (composite-PK đã chứa locale → false;
    single-PK → true).
  - `rememberSystem` / `forgetSystem` — store riêng (`CacheGate::systemStore`),
    luôn cached kể cả khi `config_debug=1`. Dùng cho 5 taxonomy + menu.
- `perLocale = true` tự nối `app()->getLocale()` vào cuối key. Nếu key còn phụ thuộc
  user group / user type / limit / ids thì tự đưa vào key trước khi gọi.
- Mọi cache liên quan **giá hiệu lực** (sản phẩm + special) PHẢI gắn `getUserGroupId()`
  vào key, vì giá hiệu lực thay đổi theo nhóm khách hàng. Quên = user nhóm này thấy
  giá user nhóm khác.
- **Cache lưu Entity, KHÔNG lưu DTO.** Repo (vd `listAllCached`, `getProductLatest`) trả
  `Collection<Model>`; controller convert DTO sau khi đọc cache. DTO là pure transform
  trên model nên gọi sau cache không phá invariant.

### Cache store decision — 3 cờ DB qua `CacheGate`

Việc CHỌN driver cache (redis / file / bypass) KHÔNG đọc `CACHE_STORE` env,
mà driven bởi 3 cờ trong bảng `setting` để admin bật/tắt runtime không cần
deploy:

| Cờ DB                  | Tác dụng                                                    |
|------------------------|-------------------------------------------------------------|
| `config_debug = 1`     | Bypass cache hoàn toàn — resolver chạy mỗi request. Trumps. |
| `config_redis_cache=1` | Dùng store `redis` (hỗ trợ tag → flush nhóm OK).            |
| `config_cache_file=1`  | Dùng store `file` (KHÔNG tag — tagged fallback non-tag).    |
| Không cờ nào           | Bypass (an toàn cho local dev mới deploy).                  |

Decision tập trung ở `App\Helpers\CacheGate::store()` trả `?Repository`.
`null` = bypass. Mọi caller cache (trait `CacheableRepository`, middleware
`CachePage`, trait `MenusClient`) đều đi qua gate này — KHÔNG đọc setting
trực tiếp để tránh drift khi đổi luật.

**Ngoại lệ duy nhất**: `ConfigDbService::getConfig()` PHẢI dùng `Cache::`
facade mặc định (driver từ `.env`, thường `database`). Lý do: gate đọc
setting → setting load qua ConfigDbService → nếu ConfigDbService đi qua
gate sẽ recursion vô tận. Setting layer là bootstrap.

`config_debug` cũng nên gate logging chi tiết (SQL log, view dump) ở các
hot path khác — convention hiện tại nhất quán: "debug bật = mọi cache off".

### Ngoại lệ — tài nguyên "load all toàn hệ thống"

5 tài nguyên load mọi page render (gắn vào view data common ở
`Controller::render`) KHÔNG chịu debug bypass — chi phí 5 SELECT mỗi request
không chấp nhận được kể cả dev mode:

| Repo / nguồn          | Method               | Cache key                |
|-----------------------|----------------------|--------------------------|
| CategoryRepository    | `listAllCached`      | `cache.categories`       |
| ManufacturerRepository| `listAllCached`      | `cache.manufacturers`    |
| FilterRepository      | `listAllCached`      | `cache.filters`          |
| ZoneRepository        | `listAllCached`      | `cache.zones`            |
| MenusClient trait     | `getMenus`           | `cache.menu`             |

Đường đi: 5 caller dùng `CacheableRepository::rememberSystem` (hoặc trực
tiếp `CacheGate::systemStore()` cho MenusClient) thay vì `rememberCache`.
`systemStore()` luôn trả `Repository`:

- `config_redis_cache=1` → redis (kèm tag `GLOBAL_TAG`).
- `config_cache_file=1`  → file.
- Mặc định                → `Cache::store()` (driver từ `.env`, thường `database`).

KHÔNG bao giờ trả null — `config_debug=1` cũng KHÔNG bypass. Dev đang debug
business code (product, review...) thì các cache đó vẫn off qua `rememberCache`;
taxonomy/menu giữ cache để dev mở trang còn nhanh.

Invalidation tương ứng dùng `forgetSystem()` (CategoryRepository, ManufacturerRepository,
FilterRepository, ZoneRepository, MenuRepository đã update). `CacheFlushObserver`
đăng ký trong `$cacheMap` tự gọi `flushCache()` của repo → forget đúng store.
Quan trọng: nếu một repo có cả cache thường VÀ cache system, `flushCache()` phải
gọi cả `forgetCache` và `forgetSystem` cho đầy đủ.

### Cache invalidation contract — observer-driven

**Cache tạo dễ, xóa khó.** Mỗi cache trong repo PHẢI có trigger invalidate
khi data nguồn mutate, nếu không user CMS sửa data xong vẫn thấy stale tới
hết TTL (30 ngày mặc định trait).

Convention:

1. Mỗi repo dùng `CacheableRepository` trait PHẢI implement public method
   `flushCache(): void`. Method tự biết flush tag/key nào. Vd
   `ProductRepository::flushCache()` xoá tag `product_root` quét cả 4 cache
   con (detail, latest, special_latest, related).

2. Cache invalidation generic dùng `App\Observers\CacheFlushObserver` —
   1 file, nhận `array $repoInterfaces` qua constructor, hook
   `saved`/`deleted`/`restored`/`forceDeleted` rồi loop gọi
   `app($iface)->flushCache()` cho từng interface. Exception bị nuốt
   (logError) — cache fail KHÔNG được phá save flow.

3. Mapping model → list repo interface TẬP TRUNG ở
   `AppServiceProvider::registerObservers()` trong bảng `$cacheMap`. Mỗi
   row: `Model::class => [Iface1::class, Iface2::class, ...]`. Loop
   `$model::observe(new CacheFlushObserver($ifaces))` đăng ký 1 lượt.
   Thêm cache mới = thêm 1 row vào bảng — không cần subclass observer.

4. **Cluster có nhiều model con** (vd Product có ProductSpecial /
   ProductVariant / ProductVariantSpecial / ProductImage / ProductCategory
   con) — TẤT CẢ model con map về CÙNG list interface
   `[ProductRepoInterface]` trong `$cacheMap`, bất kỳ thay đổi nào trong
   cluster đều flush tag root. Trade-off scope-rộng vs correctness: chọn
   correctness.

5. **Cross-entity invalidation**: relation đổi → cả 2 cache phải xoá. Khai
   báo list interface nhiều phần tử, KHÔNG cần subclass observer. Vd
   `Category::class => [CategoryRepoInterface, ProductRepoInterface]` —
   Category save → flush cả categories cache LẪN product cache (vì
   product card hiển thị tên category). Pattern áp dụng cho
   Category/Manufacturer/Filter/FilterValue.

6. **Custom observer (logic riêng, không qua generic)**: chỉ 2 trường hợp
   hiện tại:
   - `ReviewObserver` — cập nhật aggregate product (review_count,
     rating_avg, ...) bằng incremental UPDATE + flush cache.
   - `SettingObserver` — clear `ConfigDbService` cache + nếu key đổi là 1
     trong 3 cờ `config_debug/config_redis_cache/config_cache_file` thì gọi
     `CacheGate::flushAll()` wipe redis (tránh orphan khi driver flip).
   Custom = chỉ khi generic không đủ. Đa số case khác chỉ cần 1 row trong
   `$cacheMap`.

7. **Drift đã biết**: `DB::table()->update()` mass (vd seed command, CLI
   bulk import) KHÔNG fire Eloquent observer → cache stale. Phải tự gọi
   `app($repoInterface)->flushCache()` cuối job. Đã áp ở SeedReviewsCommand
   (forgetProductCache); SeedProductsCommand chưa làm — TODO.

8. **Limit của tag trên file store**: file driver không hỗ trợ tag → mọi
   `forgetCacheTagged()` no-op khi admin chọn `config_cache_file = 1`.
   Cache stale tới TTL hoặc `php artisan cache:clear`. Khuyến cáo
   production dùng redis.

Khi thêm cache mới:
- Bước 1: Thêm `flushCache()` vào repo (hoặc method per-id `flushXxxCache(int $id)`).
- Bước 2: Thêm 1 row vào `$cacheMap` ở `AppServiceProvider::registerObservers()`.
- Bước 3: Test: save model → đọc lại cache → phải miss.

## DTO — tầng output

- DTO ở `app/Data/Output/*DTO`, dùng Spatie Laravel Data v4 (`extends Data`).
- Property đặt tên **camelCase** (`metaTitle`, `priceLabel`...), không snake_case —
  snake_case chỉ thuộc về cột DB; DTO là ranh giới chuyển đổi.
- Tạo qua static `fromModel(Model $m, ...)`; controller gọi `DTO::from()` / `DTO::collect()`.
- `Lazy` (`Lazy::create`) CHỈ resolve khi DTO được `toArray()`/serialize, KHÔNG
  resolve khi blade đọc thẳng `$dto->field` (sẽ ra object `Lazy` → lỗi "could not be
  converted to string").
- Quyết định dự án: GIỮ `Lazy` cho field nặng (content, tag...); blade sẽ truy cập
  field Lazy qua dạng đã resolve thay vì đọc trực tiếp `$dto->field` — cách cụ thể
  đang bàn (chưa chốt). Hiện `BlogDTO.content`/`tag` tạm để `string` cho trang chạy;
  sẽ chuyển lại `Lazy` cùng lúc sửa blade.
- DTO `fromModel` đọc relation nào thì repository PHẢI eager-load đúng relation đó,
  nếu không sẽ N+1 âm thầm. Nếu cần load có điều kiện (vd `productFilters` chỉ load
  khi user lọc filter), DTO phải check `relationLoaded(...)` trước khi đọc để tránh
  trigger lazy load.
- **Collection con của DTO** (hasMany): dùng `Illuminate\Support\Collection` + attribute
  `#[DataCollectionOf(FooDTO::class)]`. KHÔNG dùng `?FooDTO` (singular, sẽ TypeError vì
  `collect()` trả collection chứ không phải 1 DTO). Trong `fromModel` dùng
  `FooDTO::collect($model->relation ?? collect())` để fallback an toàn khi relation
  chưa load.
- **Thumbnail ảnh** dùng trait `App\Data\Concerns\HasThumbnail` — gọi
  `$dto->thumbnail($w, $h, $module = 'web')`. KHÔNG thêm property `thumbnail` precomputed
  vì gây nhập nhằng với method.
- **Date trong DTO**: nếu blade chỉ hiển thị thì format `d/m/Y` (vd `publishedDate`);
  nếu JS đọc (vd `countDownTime`) thì format `Y-m-d H:i:s` để parser JS hiểu thẳng.
  `ProductSpecialDTO::dateEnd` đang dùng `Y-m-d H:i:s` để countdown tới giây.

## Model — quan hệ & scope

- Scope dùng chung ở trait `App\Models\Traits\HasAdvancedScopes`: `forLocale($table = '')`
  (lọc `language_code` theo locale; truyền tên bảng khi query có join), `dateStartToEnd`,
  `dateAvailable`.
- Quan hệ `description()` là `hasOne(...Description::class)->forLocale()` — tự lọc locale.
- Cần đúng MỘT bản ghi liên quan theo tiêu chí (vd special ưu tiên cao nhất): dùng
  `hasOne(...)->ofMany([...], $closure)`, không dùng hasMany rồi `->first()`.
- Repository list muốn filter/sort theo cột bảng dịch (`*_description.title`) phải tự
  join bảng đó trong `baseQuery()`.
- **Closure trong subquery raw**: `whereExists`/`whereNotExists`/`fromSub`/`joinSub`
  truyền `Illuminate\Database\Query\Builder` (KHÔNG phải `Eloquent\Builder`). Project
  dùng package `awobaz/compoships` (vì có composite PK ở `ProductFilter`,
  `ProductDescription`...) wrap thêm 1 lớp `Awobaz\Compoships\Database\Query\Builder`
  extends `Query\Builder`. Type hint sai = TypeError. Closure trong `->where(fn ($q))`
  trực tiếp trên Eloquent query thì `$q` vẫn là `Eloquent\Builder` — phân biệt rõ.

## Giá hiệu lực (effective price)

- Định nghĩa (2 nhánh):
  - **Simple product** (`product.has_variants = 0`): `effective_price =
    COALESCE(active product_special.price, product.price)`. Special là row
    `product_special` priority cao nhất đang active (đúng `user_group_id`,
    date trong `[date_start, date_end]`).
  - **Variant product** (`has_variants = 1`): `effective_price` per-variant =
    `COALESCE(active product_variant_special.price, product_variant.price)`.
    Range hiển thị / filter / sort = MIN/MAX aggregate qua các variant.
    **product_special KHÔNG còn áp cho variant product** — Hướng B (xem
    "Cluster variant special" bên dưới). DTO::formatPrice +
    Product::effectivePriceExpression + CartService::resolvePrice đều skip
    product_special cho nhánh variant.
- 2 scope trên `Product` model (signature không đổi):
  - `scopeEffectivePriceBetween(?int $min, ?int $max)` — filter range overlap
    (LOW <= filter_max AND HIGH >= filter_min). Truyền `null` cho 1 đầu để
    bỏ ràng buộc tương ứng.
  - `scopeOrderByEffectivePrice(string $dir)` — sort. ASC dùng LOW
    (min effective), DESC dùng HIGH (max effective) → tránh bias variant
    về 1 đầu.
- 2 scope đều dùng chung SQL fragment từ `protected static effectivePriceExpression(string $which)`:
  CASE WHEN nhánh variant aggregate `SELECT MIN/MAX(COALESCE(variant_special, pv.price))`,
  ELSE nhánh simple COALESCE product_special. Filter & sort luôn nhất quán
  cùng 1 nguồn.
- **Bắt buộc có index**:
  - `product_special`: `(product_id, user_group_id, priority, date_start, date_end)`
  - `product_variant_special`: `(product_variant_id, user_group_id, priority, date_start, date_end)` (= `idx_pvs_lookup`)
  - `product_variant`: `product_id` (= `idx_product_variant_product`)
  Thiếu index = N×M table scan, list sản phẩm treo.
- Tận dụng relation:
  - `Product::productSpecial()` (hasOne ofMany priority MAX + dateStartToEnd + userGroup) → DTO simple product.
  - `ProductVariant::productVariantSpecial()` (cùng pattern, tên relation thể
    hiện tên bảng `product_variant_special` theo convention dự án) → DTO + JS variant product.
  KHÔNG query thủ công lại logic này ở repo / service.

## List / phân trang / sort / filter — `QueryableRepository`

- Repository list mới extends `App\Repositories\Base\QueryableRepository`, chạy trên
  **Spatie QueryBuilder**. Cấu hình bằng cách override: `allowedFilters()`,
  `allowedSorts()`, `defaultSort()`, `allowedIncludes()`, `withRelations()`, `baseQuery()`,
  `beforeBuild()`.
- `list()` trả `LengthAwarePaginator`, đã `->appends($request->query())` sẵn để link
  phân trang giữ nguyên query string.
- **Cỡ trang**: `list()` tự đọc `per_page` từ URL (fallback `defaultPerPage = 20`),
  clamp tối đa `maxPerPage = 200`. Controller KHÔNG cần tự xử lý `per_page`.
- **Query string theo đúng convention Spatie**, KHÔNG phải convention cũ:
  - Sort: một param `sort` — `?sort=created_at` (tăng), `?sort=-created_at` (giảm).
    KHÔNG dùng `sort_field` / `sort_type`.
  - Filter: lồng trong mảng `filter` — `?filter[category_id]=5`. KHÔNG dùng hậu tố
    `_eq` (đó là kiểu `QueryableRepository` legacy).
  - Include: `?include=...`.
- Mọi sort được request PHẢI có trong `allowedSorts()`, nếu không Spatie ném
  `InvalidSortQuery`. `defaultSort()` thì không cần nằm trong danh sách.
- **Strict mode filter**: mọi key `filter[X]` PHẢI có trong `allowedFilters()` nếu
  không Spatie ném `InvalidFilterQuery`. Trường hợp cần xử lý logic gộp ngoài callback
  (vd `price_min`+`price_max` áp scope 1 lần để subquery COALESCE chỉ compute 1 lần):
  khai báo `AllowedFilter::callback('key', fn () => null)` cho "trống" + đặt logic
  thực trong `beforeBuild()`. KHÔNG bỏ khai báo.
- `withRelations()` có thể đọc request để eager-load có điều kiện (vd chỉ load
  `productFilters` khi user lọc `filter[filter_value_id]`) — tiết kiệm N+1 ở các
  trang không cần.
- Filter quan hệ many-to-many (`productCategories`, `productFilters`) dùng `whereHas`,
  KHÔNG join thủ công + `distinct`. `whereHas` không sinh duplicate row, count chuẩn.
- `cardQuery()` (helper cho hot resources `getProductLatest/Feature/Related` — trả
  query base cho UI product card) KHÔNG order mặc định — mỗi caller tự append
  `orderBy` phù hợp semantic. Tránh hardcode `orderBy('id', 'DESC')` ở base.
  Cặp đôi với `cardRelations()` (relations tối thiểu render product card) và
  `detailRelations()` (payload đầy đủ cho trang chi tiết). Trước đây tên là
  `clientQuery`/`clientRelations` — đổi do "client" overload nghĩa
  (HTTP/API/multi-tenant) sau khi rời namespace legacy `Client\InfunStudio`.
- Sort theo expression (vd giá hiệu lực): dùng `AllowedSort::callback('price', fn ($q, $desc)
  => $q->orderByEffectivePrice($desc ? 'desc' : 'asc'))`, KHÔNG `AllowedSort::field`.

## Dropdown sắp xếp / cỡ trang — trait `HasListFilterToolbar`

- KHÔNG dựng URL sort/per_page hay định nghĩa `function` trong blade. Trait
  `App\Repositories\Concerns\HasListFilterToolbar` (đã `use` sẵn trong
  `QueryableRepository`) lo việc này.
- Repo chỉ override hai điểm khai báo: `sortMenu()` trả mảng token sort theo thứ tự
  (phần tử đầu = mặc định, giữ khớp `defaultSort()`); `perPageOptions()` trả mảng cỡ
  trang. `allowedSorts()` vẫn là nguồn sự thật cho "được sort theo gì".
- Trait đối chiếu mọi token trong `sortMenu()` với `allowedSorts()`; lệch nhau thì
  ném `LogicException` ngay khi `app.debug` bật — bắt drift tại dev, không để rơi vào
  `InvalidSortQuery` lúc runtime.
- Nhãn dropdown nằm ở `resources/lang/{locale}/sort.php`, key = token (`-created_at`,
  `price`...) — chỉ là i18n, thiếu bản dịch thì hiển thị chính token.
- Controller gọi `getSortMenu()` / `getPerPageMenu()` truyền xuống view; blade chỉ
  `@foreach` dữ liệu `['token','label','active','url']`. Hai method này khai báo trong
  interface của repo (vd `ProductRepositoryInterface`, `StoreReviewRepositoryInterface`).

## Phân trang ở blade — partial `_paging`

- Partial `web.share.structure._paging` nhận `$paginator`, build link qua
  `$paginator->appends(request()->query())`. Laravel KHÔNG có method `removeQuery` —
  muốn loại key thì truyền `removeKey` (mảng) vào `links()`, partial dùng `Arr::except()`
  (hỗ trợ dot notation cho filter lồng, vd `filter.category_id`). `appends()` tự bỏ
  qua key `page`.
- **DTO + paginator**: Spatie Data v4 `collect($paginator)` trả `PaginatedDataCollection`
  KHÔNG có `total()`/`links()`. Controller giữ paginator gốc, wrap items bằng
  `$paginator->setCollection($paginator->getCollection()->map(fn ($m) => FooDTO::from($m)))`
  → blade gọi `$paginator->total()` / `links()` / `foreach` vẫn ra DTO.

## Form filter ở blade — convention input

- Tên input theo dot/bracket convention Spatie: `name="filter[category_id]"`,
  `name="filter[manufacturer_id][]"`, `name="filter[price_min]"`.
- Filter "either-or-both" (vd `in_stock` với 2 checkbox "Còn hàng"/"Hết hàng"):
  dùng `name="filter[in_stock][]"` (mảng) để cho phép user check cả 2 nghĩa "Tất cả".
  Callback Spatie nhận `(array) $value`, normalize → tập hợp duy nhất; nếu cả 2 hoặc
  không có giá trị nào hợp lệ thì skip filter. KHÔNG dùng 2 checkbox cùng `name`
  không có `[]` vì PHP chỉ giữ value cuối → mất ý định user.
- Filter giá có thể có ký tự `,.đ` từ frontend — repo helper `normalizePrice($value)`
  strip `[^\d]` và trả `?int`. KHÔNG validate ở blade/JS.
- Giữ param khác (sort, per_page) khi submit form lọc: emit hidden input cho mọi key
  `except(['filter','page'])` của `request()->query()`.

## Area

- `DetectArea` middleware đọc `area` từ route action, set vào `mystorage` +
  `channellog`, và lưu vào `$request->attributes` — KHÔNG dùng `$request->merge()`:
  trên request GET `merge()` ghi vào query bag, làm `area=web` rò ra mọi URL phân trang.
- Lấy area trong code: dùng helper `getCurrentArea()` hoặc
  `app('mystorage')->getCurrentArea()`, KHÔNG đọc `request('area')`.

## Cũ vs Mới (đang migrate)

| | Cũ (legacy) | Mới |
|---|---|---|
| Repository | `App\Repositories\Client\InfunStudio\*` | `App\Repositories\Eloquent\*` |
| Model | `App\Model\Entities\*` (số ít) | `App\Models\Entities\*` (số nhiều) |
| Method | tiền tố `_` (`_to`, `_processMetaSeo`) | không tiền tố |
| Quan hệ desc | `blogDescription`, `productDescription` | `description` |
| Cache | `Cache::tags()` + redis cứng | `CacheableRepository` |
| Query string | `*_eq`, `sort_field`/`sort_type` | Spatie: `filter[...]`, `sort=` |
| Jobs | `App\Jobs\Client\InfunStudio\*` extends `BaseInfunStudioJob` | `App\Jobs\*` implements `ShouldQueue` + 4 trait Laravel |
| Job mailer | `getMailer()` magic + method `_handle()` | inject `JobMailer` qua `handle(JobMailer)` |
| Validate input | `$repo->getValidator()->validateCreate(...)` | `App\Http\Requests\Web\*Request` (FormRequest) |
| Shipping fee | `App\Services\FeeShipService` extends `BaseService` | `App\Services\Checkout\ShippingFeeService` (Guzzle inline) |
| Cart | `App\Helpers\Cart` đọc `product_option_value`/`_2` legacy | `App\Services\CartService` resolve `product_variant_id` cluster |
| Cart logic trong controller | 4 trait `CheckoutMarketing`/`CheckoutPayment`/`CheckoutTotal`/`CreateOrder` | service `App\Services\Checkout\*` inject qua DI |
| Config "có thể override" | `getCoreConfig('x')` cứng | `setting('x')` — fallback DB → core config |
| Helper user id | `getUserLoginId()` | `(int) getCurrentUserId()` |
| Helper cookie | `getCookie('x')` | `request()->cookie('x')` |

`ProductController` đang dùng pattern hỗn hợp: `getList()` và `special()` đã chuyển
sang DTO + Spatie, `index` còn theo style cũ. Khi migrate `index`, dùng `getList()` /
`special()` làm mẫu.

## Helper toàn cục thường dùng

Khai báo trong `app/Common/Common.php`:

`getCoreConfig()`, `getModuleConfig()`, `getConfigDb()`, `getUserGroupId()`,
`getUserType()`, `getUserLoginId()`.

`resolveSlug(?string $slug, ?string $title): string` — trả slug đang có; nếu rỗng
(null hoặc chuỗi trắng) thì tự sinh từ title bằng `Str::slug()`. Dùng `filled()` nên
bắt được cả chuỗi rỗng, khác toán tử `??`. Dùng chung cho DTO/model khi build URL
từ title (xem `BlogDTO`, `BlogCategoryDTO`, `BlogTagDTO`).

## Mẫu tham chiếu: `getProductLatest`

Luồng chuẩn cho dữ liệu dùng chung + có cache:

1. `Controller::getProductLatest(int $limit = 6)` — facade gọn, chỉ delegate
   `$this->productRepo->getProductLatest($limit)`.
2. `ProductRepository::getProductLatest()` — query thật + cache qua `rememberCacheTagged()`.
   Mirror đúng `getProductFeature()`, tái dùng `cardRelations()`.
3. Khai báo trong `ProductRepositoryInterface`.

Relation `Product::description()` đã tự áp `->forLocale()`, không cần lọc locale thủ công.

## Mẫu tham chiếu: trang list Product (filter/sort theo giá hiệu lực)

`ProductController::getList()` + `ProductRepository::list()` là mẫu đầy đủ cho:
- Filter Spatie + filter expression (giá hiệu lực) qua `beforeBuild()`.
- Sort qua expression scope (`AllowedSort::callback` + `orderByEffectivePrice`).
- Conditional eager-load (`productFilters` chỉ khi user lọc).
- Wrap paginator giữ API `total()/links()`, items thành DTO.
- Truyền `sortMenu`/`perPageMenu` xuống view.
- Toolbar lọc ở blade theo convention `filter[...]`.

`getProductRelated($ids)` minh hoạ pattern giữ thứ tự ids (admin sắp ở bảng
`product_related`) bằng `orderByRaw('FIELD(product.id, ...)')` + cache key dùng
mảng đã sort.

## Mẫu tham chiếu: trang khuyến mãi (`ProductController::special`)

Trang khuyến mãi = trang list Product + **1 ràng buộc**: product phải có
`ProductSpecial` active. Không tạo repo riêng; tái dùng pipeline list.

- `Product::scopeHasActiveSpecial()` — `whereHas('productSpecials', fn ($q) =>
  $q->where('user_group_id', getUserGroupId())->dateStartToEnd())`. Bắt buộc tái dùng
  `dateStartToEnd()` thay vì viết lại điều kiện ngày — `productSpecial()` relation
  (eager-loaded vào DTO) cũng dùng scope này; nếu lệch toán tử so sánh (vd `<=` vs
  `<` cho `date_start`), product lọt vào danh sách khuyến mãi nhưng relation trả
  null → giá hiển thị sai (giá gốc thay vì giá KM).
- `QueryableRepository::list(?Request $r = null, ?int $perPage = null, ?\Closure
  $modifyBase = null)` — closure thứ 3 (optional) append scope vào base query SAU
  `beforeBuild()` mà không cần duplicate `buildQuery`. Backward-compatible với mọi
  caller cũ.
- `ProductRepository::getListSpecial()` = `$this->list($r, null, fn ($q) =>
  $q->hasActiveSpecial())`. 1 dòng, tận dụng nguyên filter/sort/cache/eager-load
  của `list()`.
- `ProductRepository::getProductSpecialLatest(int $limit)` — mirror
  `getProductLatest()` cho homepage, append `hasActiveSpecial()` trước `orderBy
  sort_order/created_at DESC`. Cache theo `getUserGroupId() + getUserType()`.
- Controller `special()` ~10 dòng, dùng lại view `web.product.special.blade.php`
  (chỉ khác title/breadcrumb so với `web.product.list`, tái dùng nguyên partial
  `_sort_by`, `_product`, `_paging`, `_side_bar`, `_schema_product_list`).

`App\Repositories\Eloquent\ProductSpecialRepository` + Interface giờ KHÔNG còn nằm
trên đường đi của trang khuyến mãi / homepage. Giữ lại tạm cho backward compat
(`ProductController::__construct` còn inject), sẽ gỡ khi chắc không còn caller.

## Drift đã biết quanh "active special" (cần thống nhất)

3 nguồn so sánh ngày trên `product_special` không đồng bộ:

| Nguồn | `date_start` | `date_end` |
|---|---|---|
| `HasAdvancedScopes::scopeDateStartToEnd` | `<` strict | `>` strict |
| `Product::effectivePriceExpression` (raw SQL trong scope giá hiệu lực) | `<=` | `>=` |
| Legacy `_buildQueryForProductSpecials` (đang phế) | `<=` | `>` |

`scopeHasActiveSpecial` đang khớp `dateStartToEnd` (đảm bảo relation + scope filter
nhất quán). Nhưng vẫn còn drift với `effectivePriceExpression`: ở biên `date_end ==
now()`, scope filter loại product nhưng nếu user vẫn vào được trang chi tiết thì
COALESCE giá hiệu lực vẫn lấy giá KM. Cần chọn 1 cặp toán tử và sync cả 3 nơi.

## Việc còn nợ trong `ProductRepository`

- `getProductSpecials(array $productIds)` — tên gây hiểu lầm, thực ra là `Product`
  lookup theo IDs. Không cache, không gắn relations. Đổi tên `getByIds()` hoặc gỡ
  nếu không còn caller.
- `getProductFeature(int $limit)` — chưa cache, trong khi `getProductLatest` /
  `getProductRelated` đều cache qua `rememberCacheTagged`. Drift hành vi.

## Pint (`pint.json`)

Preset Laravel + bổ sung rule khoảng trắng quanh `,` trong array:
`whitespace_after_comma_in_array` (single space), `no_whitespace_before_comma_in_array`,
`no_trailing_comma_in_singleline`, `trim_array_spaces`. Mục đích: chuẩn hoá các
trường hợp `[ a,b ,c ,]` về `[a, b, c]`.

## Seed dữ liệu test

`php artisan products:seed 50000` — command ở `app/Console/Commands/SeedProductsCommand.php`
sinh dữ liệu sản phẩm giả lập (product + product_description + product_category +
product_special + product_filter) cho test filter/sort/paging. Dùng bulk insert raw
DB::table, tắt model events + FK checks tạm thời. Đầy đủ flag: `--chunk`,
`--truncate`, `--no-special`, `--no-filter`. Phân bố giá realistic (60% 100k–1tr,
25% 1–3tr), ~30% có special active.

Sau khi seed: chạy `ANALYZE TABLE product, product_special, product_filter,
product_category;` + `php artisan cache:clear`.

## Ảnh

Dùng `intervention/image` v3 — không có facade `Image`. Trong `MyStorage::resizeImage`:
khởi tạo `ImageManager::gd()`, đọc `->read()`, resize `->coverDown()`, encode
`->encodeByPath()`. Ảnh mặc định (`no_img`) nằm trong `public/` — trả URL bằng `asset()`,
không qua storage disk (disk `public` sẽ chèn `/storage` vào đầu).

## Mẫu tham chiếu: trang chi tiết Product (`ProductController::index`)

Route đi qua `HomeController::index($slug)` → `getControllerBySlug()` parse slug
thành `[controllerClass, $id]`, `forward()` về `ProductController::index($id)`.

Pattern slim (đã rewrite theo cluster variant — schema mới):

- Data fetch + cache: `productRepo->getProductDetail($id)` — eager-load song
  song HAI nhánh option trong cùng 1 query:
    * Variant role: `productVariants` (sort `is_default DESC, sort_order ASC,
      id ASC`) + `productVariants.productVariantAttributes.optionValue.description`
      + `productVariants.stock` + `productVariants.description` +
      `productOptionDefinitions.option.description`.
    * Custom field role: `productOptions.option.description` +
      `productOptions.option.optionValues.description` +
      `productOptions.productOptionValues.optionValue.description`.
  Cache `rememberCacheTagged([product_root, products{id}])`, key gắn
  `getUserGroupId() + getUserType()`.
- View increment: `productRepo->incrementViewed($id)` — atomic `UPDATE` qua
  `DB::table('product')`, bỏ qua model events. Tách khỏi `getProductDetail`
  để cache không hit mỗi lần tăng view.
- Related products: `productRepo->getProductRelatedByProductId($id, 4)` —
  encapsulate `ProductRelated` lookup, delegate sang `getProductRelated()` đã
  cache.
- Option tree + variant matrix build: `optionService->build($entity)` — đổi
  signature nhận `Product` (không phải raw productOptions). Trả mảng 4 phần
  `{options, imageOptions, variantMatrix, defaultVariant}` (xem mục
  "ProductOptionService::build" bên dưới).
- DTO + view: `ProductDTO::from($model)` — DTO mở rộng với `ratingRounded`,
  `weightUnit`, `gallery` (eager-loaded từ `productImages`, check
  `relationLoaded` tránh N+1 ở list page), `linkSaleCustom` (decode JSON sẵn),
  `hasVariants` + `minVariantPrice` + `maxVariantPrice` (denormalized aggregate
  từ schema mới). `priceLabel` ưu tiên giá variant (range `min – max` khi
  lệch), fallback `product.price` cho simple product.
- Wishlist: `getProductUserWishlist($id)` ngay trong controller — đơn lookup,
  không đủ phức tạp để cần repo.
- Blade: `web.product.index` đọc DTO + truyền tiếp `$options`,
  `$variantMatrix`, `$defaultVariant` xuống view; inject 3 biến JS toàn cục
  (`options`, `variantMatrix`, `defaultVariant`) qua `@section('script_header')`
  cho phần JS variant handler đọc.

## Schema cluster variant (refactor `product_option_value` + `_2`)

Bảng cũ `product_option_value` + `product_option_value_2` thiết kế theo "cấp
1/cấp 2" cứng, không scale n level, có placeholder row khi product chỉ có 1
cấp variant, dùng `(value, prefix '+'/'-')` thay DECIMAL signed. Thay bằng
cluster mới (migration `2026_05_30_100000–100009`):

- **`product_variant`**: 1 row = 1 tổ hợp. Cột `price` là giá tuyệt đối
  (KHÔNG delta). `attribute_signature CHAR(32)` UNIQUE chống trùng tổ hợp —
  app layer compute MD5 (option_value_id list sort theo option_id) khi save
  pivot. `is_default`, `sort_order`, soft delete.
- **`product_variant_attribute`**: pivot
  `(product_variant_id, option_id, option_value_id)`, PK
  `(product_variant_id, option_id)` — cưỡng chế "1 option có max 1
  value/variant" ở DB. `option_id` denormalize từ `option_value.option_id` để
  PK enforce + query nhanh không phải join `option_value`. Service layer phải
  derive `option_id` từ `option_value_id` khi attach để chống drift.
- **`product_variant_description`**: i18n label tự đặt cho variant (optional;
  không có row → frontend ghép từ `option_value.description.name`).
- **`product_stock`**: tách tồn kho khỏi `product_variant` — `on_hand`,
  `reserved`, `warehouse_id`, `version` (optimistic lock). UNIQUE
  `(product_variant_id, warehouse_id)`. Lý do tách: stock có concurrency cao
  + vòng đời ngắn, để chung gây lock contention với read trang chi tiết.
- **`stock_movement`**: append-only audit log — `type` (receive/sale/reserve/
  release/adjust/transfer), `quantity_change` signed, `on_hand_after`
  snapshot, `reference_type`/`reference_id` polymorphic. Rebuild được
  `on_hand` từ SUM nếu nghi data corruption.
- **Declare "product có option X" vẫn dùng legacy `product_option`** —
  KHÔNG tạo bảng `product_option_definition` riêng (đã trial và drop ở
  migration 100005). Lý do: legacy `product_option(product_id, option_id,
  value, required)` đã đủ vai trò declaration; tạo bảng thứ 2 chỉ vì
  "có role variant" là duplication. Dispatch theo `option.role`:
    * `ROLE_VARIANT` → `product_option.value` NULL, values lấy từ
      `product_variant_attribute` pivot.
    * `ROLE_CUSTOM_FIELD` → `product_option.value` là default, user override
      lúc checkout.
  `ProductOptionService::buildVariantOptions/buildCustomFieldOptions` cùng
  đọc `$product->productOptions`, filter theo `$po->option->role`. Repo
  `detailRelations()` chỉ eager-load 1 nhánh `productOptions.*` + nhánh
  `productVariants.*` cho values. Model `ProductOptionDefinition` còn lại
  như shim throw exception — bắt caller cũ chưa migrate.
- **`product` thêm 3 cột**: `has_variants` (short-circuit cho simple
  product), `min_variant_price`/`max_variant_price` (denormalized aggregate,
  cập nhật qua observer trên `ProductVariant::saved/deleted`). Index
  `(has_variants, min_variant_price)` tối ưu filter list page.

`option.role TINYINT UNSIGNED` (migration `100008`) thay parse `type`:

- `0 = custom_field` (text/date/file/phone/email — user điền lúc checkout, KHÔNG tạo SKU)
- `1 = variant` (radio/checkbox/select/image — tạo SKU)
- `2..9` reserved future (bundle_part, addon, gift, ...)
- `CHECK (role IN (0, 1))` cưỡng chế ở DB (MariaDB 10.2.1+ enforce thực sự).
- Model PHẢI khai báo hằng số: `Option::ROLE_VARIANT`, `Option::ROLE_CUSTOM_FIELD`.
  Code dùng hằng số, KHÔNG literal `1` — tránh magic number.

## Convention DB / migration

**Tên cột FK**: prefix full table name. `product_variant_id` (KHÔNG
`variant_id`), `option_value_id` (KHÔNG `value_id`). Đọc DDL biết liên kết
bảng nào ngay. Khớp với convention dự án (xem
`product_description.product_id`, `product_option_value.product_option_id`).

**Type FK column phải khớp CHÍNH XÁC** bảng được reference (type + signedness +
size). Legacy ID là `INT(11) SIGNED` (OpenCart convention). Migration mới FK
tới legacy:

```php
$table->integer('product_id');  // INT signed — khớp product.id
$table->foreign('product_id')->references('id')->on('product');
```

KHÔNG `unsignedBigInteger`/`unsignedInteger` cho FK tới legacy → MySQL/MariaDB
ném errno 150 "Foreign key constraint is incorrectly formed". Bảng mới nội
bộ cluster (vd `product_variant`) tự do dùng `bigIncrements` + FK
`unsignedBigInteger` — nhất quán Laravel modern.

**Soft delete consistency**: bảng cha có `deleted_at` thì bảng con cũng phải
có. Vd `option.deleted_at` đã có, `option_value.deleted_at` cần thêm để
không mất trace khi xoá.

**FK ON DELETE policy**:
- Legacy reference (product, option, option_value) → `restrictOnDelete()`
  (chặn xoá nếu còn ref) — bảo vệ data legacy.
- Nội bộ cluster mới (product_variant → product_stock/attribute/description)
  → `cascadeOnDelete()` (xoá variant tự dọn con).

**Guard "đã tạo chưa"**: dùng `Schema::hasTable('x')`, KHÔNG dùng
`DB::table('x')->exists()` — nó query data trong bảng, fail 1146 nếu bảng
chưa tồn tại (lần migrate đầu). Bug này có sẵn trong Laravel 11 default
`create_jobs_table`, `create_users_table`, `create_personal_access_tokens_table` —
đã fix.

**Migration timestamp đặt thứ tự**: cluster variant dùng `2026_05_30_100000–
100009`, migration convert engine `2026_05_29_000000` (sớm hơn 30 để chạy
trước cluster có FK). Laravel sort theo full filename lexically.

## MyISAM → InnoDB convert

Schema legacy là MyISAM (import từ dump OpenCart cũ). MyISAM KHÔNG support
FK, transaction, row-level locking → mọi FK migration sẽ fail. Migration
`2026_05_29_000000_convert_legacy_tables_to_innodb.php` convert toàn bộ.

Gotcha:

- `ALTER TABLE x ENGINE=InnoDB` đơn thuần FAIL với **errno 140 "Wrong create
  options"** nếu bảng có `ROW_FORMAT=FIXED` (MyISAM-only), `PACK_KEYS=1`,
  `DELAY_KEY_WRITE=1`, hoặc `CHECKSUM=1`. Phải strip cùng 1 câu ALTER:
  ```sql
  ALTER TABLE x
    ROW_FORMAT=DYNAMIC, PACK_KEYS=DEFAULT,
    DELAY_KEY_WRITE=0, CHECKSUM=0,
    ENGINE=InnoDB;
  ```
- Bảng có `DATA DIRECTORY` / `INDEX DIRECTORY` custom path: ALTER không tự
  copy file, phải xử lý thủ công.
- FULLTEXT index preserved tự động qua ALTER ENGINE (MariaDB 10.0.5+,
  MySQL 5.6+). Behavior search khác đôi chút (`innodb_ft_min_token_size=3`
  vs `ft_min_word_len=4`, stopword list khác) — acceptable cho test data;
  production cần A/B test query search hot trước convert.
- `failed_jobs` trong DB này cũng MyISAM (do import từ source cũ chứ không
  phải Laravel tự tạo). Convert kèm.

## PHP opcache với migration

XAMPP82 default bật opcache cho cả CLI. Sửa file migration → opcache vẫn
serve version cũ → error message thấy SQL cũ chứ không phải code mới đã sửa.
Fix:

- Override khi chạy: `php -d opcache.enable_cli=0 artisan migrate ...`
- Hoặc chạy SQL trực tiếp (test data, nhanh hơn migration approach):
  ```sql
  SELECT CONCAT('ALTER TABLE `', TABLE_NAME, '` ',
    'ROW_FORMAT=DYNAMIC, PACK_KEYS=DEFAULT, DELAY_KEY_WRITE=0, CHECKSUM=0, ENGINE=InnoDB;')
  FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = DATABASE() AND ENGINE = 'MyISAM';
  ```
- Restart Apache KHÔNG giúp CLI — CLI có process riêng với opcache riêng.

## Account flow (refactor 2026-05-31)

AccountController đã được rewrite theo phân tầng Controller → Service → Repository.
Trước đây controller extends BaseInfunStudioController (legacy không tồn tại) ở namespace
App\\Http\\Controllers\\Client\\InfunStudio, dùng trait CheckoutPayment legacy + validator
legacy $repo->getValidator()->validateX(...). Giờ đã chuẩn cùng pattern Checkout.

### Phân tầng

- Controller: App\\Http\\Controllers\\Web\\AccountController extends App\\Http\\Controllers\\Controller.
  Constructor inject 4 service + 2 repo qua promoted properties. Action xử lý cả
  GET (render form) lẫn POST (Route::any) — POST branch resolve FormRequest qua
  container app(FormRequest::class)->validated() để trigger validateResolved của
  FormRequestServiceProvider. KHÔNG type-hint FormRequest ở method signature vì sẽ
  chạy validation cả trên GET (rules required → fail trắng trang).

- Service ở App\\Services\\Account\\*:
  * AccountService — updateProfile (transaction 2 bảng user + user_phone, reset
    is_verify=0 khi user đổi sang số khác), changePassword (lockForUpdate row user
    + Hash::make), updateNewsletter, và cancelOrder (orchestrate RefundService +
    OrderRepository + OrdersCancel + OrdersHistory trong 1 transaction).
  * AddressService — CRUD + sync cookie cookie.user.address mỗi mutate;
    applyQuickAddress cho endpoint POST /account/add-address (visitor chưa login
    lưu địa chỉ vãng lai vào cookie, login user set 1 trong sổ địa chỉ là default).
  * WishlistService — toggle/remove + sync session.total_wishlist (header badge phụ
    thuộc → mỗi mutate phải gọi syncSessionCounter để không drift).

- Service App\\Services\\Checkout\\RefundService — tách khỏi CheckoutPaymentService
  vì refund chỉ dùng cho luồng cancel order. Stateless wrapper quanh ZaloPay::refund
  + getRefundStatus:
  * needsRefund(Orders) — order có zp_trans_id + chưa có zp_refund_id → cần refund.
  * refund(Orders, $description) — trả [ok, mRefundId]. ZaloPay trả return_code=2
    → [false, null], caller throw refund_failed.
  * getStatus($mRefundId) — enum string success/processing/unknown.
  * KHÔNG ghi DB — caller (AccountService::cancelOrder) persist zp_refund_id qua
    OrderRepository::upsertOrder.

- Repository ở App\\Repositories\\Eloquent\\*:
  * UserRepository, UserPhoneRepository, UserAddressRepository, UserWishlistRepository
    — REWRITE 4 stub broken cùng tên ở namespace App\\Repositories\\Client\\InfunStudio
    (extends BaseInfunStudioRepository không tồn tại, validator legacy không tồn tại).
    Auto-bind interface→implementation qua AppServiceProvider::registerRepository().
  * OrderRepository thêm: getListForUser (phân trang user-scoped), getDetailForUser
    (eager-load đầy đủ), recordCancel (insert orders_cancel).

- FormRequest ở App\\Http\\Requests\\Web\\ (5 mới): AccountUpdateProfileRequest,
  AccountChangePasswordRequest (logic withValidator->after kiểm Hash::check(old_password)
  chỉ khi user KHÔNG có type_register — social login bypass), AccountAddressRequest,
  AccountCancelOrderRequest, AccountAddAddressRequest.

- DTO mới: UserDTO (REWRITE từ shape cũ id/full_name/address/avatar sang camelCase
  đầy đủ + phone từ relation userPhone + sex/newsletter/typeRegister), UserAddressDTO,
  WishlistItemDTO (flatten product cần cho list, KHÔNG dùng ProductDTO đầy đủ),
  OrderDTO + OrderItemDTO + OrderItemOptionDTO + OrderTotalDTO. OrderDTO precompute
  4 cờ derived (isPaymentWaiting, isPaymentSuccess, canCancel, repaymentAllowed)
  để blade chỉ check boolean.

### Blade view

Đã migrate resources/views/web/account/*.blade.php:
- @extends('client.infunstudio.layouts.main_account') → @extends('web.layouts.main_account').
- $entity->productDescription → $entity->description qua DTO (camelCase property).
- $entity->ordersTotal raw → $entity->totals (Collection<OrderTotalDTO>).
- Pagination link partial: client.infunstudio.share.structure._paging → web.share.structure._paging.
- Form thêm @csrf (legacy chưa có).

### Helper migration

- getUserLoginId() (legacy) → (int) getCurrentUserId().
- getCookie($key, $default) (legacy wrapper) → request()->cookie($key, $default).
- _processMetaSeo('_buildForSeoByConfig', ...) → processMetaSeo('buildForSeoByConfig', ...).
- _isPOST() → $request->isMethod('post').
- _removeCookieSessionUser() → auth()->logout() + session()->invalidate() +
  session()->regenerateToken() + AddressService::clearVisitorCookie().

### Việc còn nợ Account

- App\\Validators\\Module\\Client\\InfunStudio\\* (UserValidator, UserAddressValidator,
  UserPhoneValidator, UserWishlistValidator, OrderValidator) không còn caller sau khi
  rewrite. Xoá thủ công khi tiện.
- AccountController::detailOrder còn inject ad-hoc CheckoutPaymentService qua app(...)
  cho luồng ZaloPay redirect sau repayment — vì DI 6 dependency đã đủ dày. Acceptable.
- Route::any cho cả GET + POST cùng method controller — nếu cần REST hơn, split
  thành GET edit + POST update để type-hint FormRequest trực tiếp ở signature.

## Schema `product_image` cluster (refactor 2026-06-03)

Bảng cũ chỉ có `id, product_id, image, sort_order, timestamps` — không đủ cho
e-commerce hiện đại. Refactor thêm:

```
product_image (
    id, product_id INT FK CASCADE,
    product_variant_id BIGINT NULL FK CASCADE  -- NULL = gallery chung product,
                                                  != NULL = ảnh riêng variant
    image VARCHAR(255) NOT NULL,
    alt VARCHAR(255) NULL,            -- SEO + a11y, blade fallback product.name
    title VARCHAR(255) NULL,          -- tooltip / lightbox caption
    type VARCHAR(16) NOT NULL,        -- main / gallery / thumbnail / zoom / 360
    width, height, file_size, mime,   -- metadata (responsive srcset, lazy-load)
    sort_order, is_active,            -- visual order + soft-hide
    timestamps, deleted_at
)
INDEX (product_id, type, is_active, sort_order)
INDEX (product_variant_id, sort_order)
```

Migration `2026_06_03_000002_refactor_product_image_table.php`. Cleanup
`product_id=0` orphan + backfill `created_at` NULL. **Vẫn giữ tương thích:**
`product.image` legacy single field + `product_variant.image` single field
KHÔNG bị thay — `product_image` là EXTENSION cho gallery.

Discriminator `product_variant_id`:
- `IS NULL` → ảnh dùng chung mọi variant → `ProductDTO::resolveGallery` filter
  + push vào `$product->gallery`.
- `IS NOT NULL` → ảnh riêng variant (vd áo xanh có 5 ảnh) →
  `ProductOptionService::buildVariantGallery` group theo variant_id, JS swap
  cả gallery khi user chốt variant qua `window.variantGallery[variant_id]`.

Eager-load filter ở `ProductRepository::detailRelations()`:

```php
'productImages' => fn ($q) => $q->where('is_active', true)
    ->whereIn('type', [
        ProductImage::TYPE_MAIN,
        ProductImage::TYPE_GALLERY,
        ProductImage::TYPE_ZOOM,
    ])
    ->orderBy('sort_order'),
```

Load cả 2 nhánh (NULL + NOT NULL variant_id) → DTO + Service tự filter theo
trách nhiệm. Index `(product_id, type, is_active, sort_order)` cover query.

## Schema `product_option` composite PK + `product_option_value` cleanup (2026-06-03)

`product_option` **KHÔNG có cột `id`** — PK composite `(product_id, option_id)`.
Model:

```php
public $primaryKey = ['product_id', 'option_id'];
public $incrementing = false;
```

Quan hệ tới child tables (`product_option_value`, `orders_product_option`)
dùng **composite FK** qua trait `Awobaz\Compoships\Compoships` (đã ở Base):

```php
public function productOptionValues() {
    return $this->hasMany(
        ProductOptionValue::class,
        ['product_id', 'option_id'],  // FK cols on child
        ['product_id', 'option_id'],  // PK cols on parent
    );
}
```

`product_option_value` cleanup (migration
`2026_06_03_000001_refactor_product_option_value_post_drop_v2.php`):
- TRUNCATE (dangling `product_option_id` legacy không tra ngược được option_id)
- Drop cột `product_option_id` (FK trỏ tới cột `id` không tồn tại của
  `product_option`)
- Rename `option_value_1_id` → `option_value_id` (suffix `_1` mất nghĩa sau
  khi drop bảng `_2`)
- Add `option_id` INT NOT NULL → composite FK
  `(product_id, option_id) → product_option`
- UNIQUE `(product_id, option_id, option_value_id)` chặn duplicate trong picker

`ProductOptionService::buildCustomFieldOptions` line 196 dùng
`$option->id ?? $po->option_id` làm `$option['id']` outer key cho blade
form — đồng nhất với variant role (cũng dùng `option_id` làm X), blade
`option[X][...]` pattern không đổi.

## Schema `product_filter` (add filter_id 2026-06-03)

Legacy chỉ có `(product_id, filter_value_id)`. Migration
`2026_06_03_000000_add_constraints_to_product_filter.php` thêm:

- Cột `filter_id` INT NOT NULL (denormalize từ `filter_value.filter_id`,
  index cover query "products in filter group X")
- PK `(product_id, filter_value_id)` (semantic OpenCart: 1 product có thể nhiều
  value trong cùng filter group, vd áo cả Size M lẫn L)
- FK CASCADE product / RESTRICT filter / RESTRICT filter_value
- **Trigger `BEFORE INSERT/UPDATE`** auto-derive `filter_id` từ `filter_value`
  → app code chỉ cần insert `(product_id, filter_value_id)`, trigger set
  `filter_id` tự động → chống drift DB-level.

## Refactor `ProductImage` cluster — Service hooks

`ProductOptionService::buildOptions` output mở rộng:

```php
return [
    'options', 'imageOptions', 'variantMatrix', 'defaultVariant',
    'variantGallery' => [$variant_id => [['image', 'alt', 'sort_order'], ...]],
];
```

`buildVariantGallery(Product)`:
- Đọc `productImages` (đã eager-load + filter active + visible types ở repo)
- Filter `product_variant_id != NULL`
- Group theo variant_id

`buildVariantOptionValues` precompute `variantImageByValueId`:
- Map `option_value_id → string image` từ `product_variant.image`
- Set-based detection: 1 value chỉ "đại diện ảnh" khi mọi variant chứa nó
  cùng image (`count(set) === 1`). Size M co-occurs với blue.jpg + red.jpg
  → set size > 1 → loại; Blue chỉ co-occurs blue.jpg → giữ.
- Algorithm: `array_map(array_key_first, array_filter($imagesPerValue, count===1))`

`imageOptions[]` shape có thêm `alt` field → cùng shape `$product->gallery` →
blade `array_merge(gallery, imageOptions)` xong loop không cần guard `?? ''`.

## JS interaction pattern — trang chi tiết product (slick + variant)

File `public/web/js/style.js`. Convention chốt sau session 2026-06-03:

**Slick config:**
- Main slider: `fade: true, speed: 0, infinite: false` — cut instant, không
  slide ngang, không clone wrap. UX Shopee.
- Thumb strip: `slidesToScroll: 1, infinite: false, speed: 0` — boundary
  cứng, hover qua edges không glitch do clone.

**Variant swatch click → main image:**
- `swapMainImage(src)` ưu tiên `goToImageBySrc(src)` — navigate slick tới
  slide có src khớp, KHÔNG mutate src của slide (tránh bug "thumb đầu hiển
  thị variant image" do src persist).
- Slide variant đã có sẵn trong `$images = $product->gallery + imageOptions`
  → slickGoTo tìm được.
- Fallback: nếu không tìm thấy slide khớp, mới mutate src.

**Variant click toggle off:**
- Click lại swatch đã `.active` → de-select: `$input.prop('checked', false)`,
  remove `.active`, `resetMainSlider()` về slide 0, `applyVariant(defaultVariant, false)`
  reset price/stock.
- Native radio không hỗ trợ uncheck via click → JS handler dùng
  `$wrapper.hasClass('active')` để detect "was checked" rồi force uncheck.

**Hover thumb → preview swap (KHÔNG dùng slickGoTo):**
- `mouseenter .slick-slide:not(.slick-cloned)` → swap `$mainImg.src` trực
  tiếp + save `data('hoverBaseline')` lần đầu.
- URL derive: thumb dùng `/147x147/`, main dùng `/1000x1000/`, regex replace
  pattern path. Cùng MyStorage cache logic.
- `mouseleave .slider-nav-thumbnails` → restore `src` từ baseline.
- KHÔNG slickGoTo vì `asNavFor` reciprocal sync làm strip auto-scroll →
  "loạn" khi user di chuột nhanh.
- Visual indicator: toggle `.is-hovering` lên strip + `.is-hover-active` lên
  thumb. CSS mirror `.slick-current` visuals (border cam + tam giác đỉnh).
  Mouseleave gỡ class → `.slick-current` thật hiện lại.

**Shopee-style availability (variant out-of-stock filter):**
- `refreshAvailability()` rule: value V của option O bị disable CHỈ khi:
  1) Có selection ở option khác (`hasSelection=true`), VÀ
  2) O chưa được chọn (`optionAlreadySelected=false`), VÀ
  3) Combo `selected ∪ {O: V}` không có in-stock variant.
- Init (chưa chọn gì) → ALL enable. Option đã active → mọi value enable
  (user switch tự do trong cùng option).
- CSS `.out-of-stock` (custom.css): opacity 0.45 + diagonal stripe overlay
  + grayscale + line-through cho text + dashed border. Phân biệt rõ với
  `.active` (border cam solid).

**`applyVariant(variant, swapImg = true)`:**
- Init `applyVariant(defaultVariant, false)` — set price/stock nhưng KHÔNG
  swap ảnh. Main slider giữ `$images[0]` = product.image → khớp thumb[0].
- User click variant → `applyVariant(variant, true)` swap ảnh.
- Tránh bug "main image khác thumb đầu" do auto-swap on init.

## `MyStorage::resizeImage` — fallback `public_path()`

Code đọc qua `Storage::disk('public')` → map tới `storage/app/public/`. Ảnh
seed + legacy nhiều nơi nằm thẳng trong Laravel `public/` (vd `public/seed/`,
`public/data/`, `public/catalog/`). Resize logic giờ check tuần tự:

```
1. Storage::disk('public')->exists($path)?    → use disk source
2. is_file(public_path($path))?               → use public source ($fromPublic = true)
3. no_img fallback                            → check is_file(public_path($noImg))
4. return asset($noImg)                       → broken link nếu cả 3 đều miss
```

`storage:link` không bắt buộc cho ảnh seed `public/seed/*` vì bước 2 đã cover.

## Naming convention: `cardRelations` (rename từ `clientRelations`)

(Đã ghi ở mục "List / phân trang" line 133-138). Cặp method ở
`ProductRepository`:
- `cardQuery()` + `cardScope()` + `cardRelations()` — UI thẻ ngắn
  (list/related/latest/feature/special)
- `detailRelations()` — UI trang chi tiết (mở rộng từ cardRelations)

## Naming variable / view-data key — ngữ nghĩa rõ, không generic

**Rule 1 — Key view data + property name PHẢI tự document được entity nguồn.**
Nhìn key là biết đang xử lý dữ liệu nào. KHÔNG dùng từ chung như `criteria`,
`tags`, `items`, `data`, `list`. Bắt buộc kèm tiền tố entity:

```php
// BAD — key sa-mô-rai, đọc blade không biết đâu là review criteria,
// đâu là filter criteria, đâu là gì khác.
return $this->render('web::product.index', [
    'criteria'         => ReviewCriteriaDTO::collect($this->reviewRepo->getActiveCriteria()),
    'tags'             => ReviewTagDTO::collect($this->reviewRepo->getActiveTags()),
    'criteriaAverages' => $this->reviewRepo->getCriteriaAverages($id),
]);

// GOOD — tự document.
return $this->render('web::product.index', [
    'reviewCriteria'         => ReviewCriteriaDTO::collect($this->reviewRepo->getActiveCriteria()),
    'reviewTags'             => ReviewTagDTO::collect($this->reviewRepo->getActiveTags()),
    'reviewCriteriaAverages' => $this->reviewRepo->getCriteriaAverages($id),
]);
```

Áp cho mọi tầng: view data, controller property, service method param, DTO
property, session/cookie key. Trade-off verbose vs maintain: chọn maintain.

Ngoại lệ — context đã rõ ràng từ scope class: trong `ReviewRepository`
method `getActiveCriteria()` không cần đặt `getActiveReviewCriteria()` vì
class name đã carry context.

**Rule 2 — Closure parameter ngắn OK, biến ngoài closure phải đầy đủ.**

Closure là scope hẹp 1-2 dòng — variable name `$m`, `$r`, `$q`, `$e` đọc
được vì context ngay sát:

```php
// OK — $m thấy ngay context map → ProductDTO::from(Model)
$entities->getCollection()->map(fn ($m) => ProductDTO::from($m));

// OK — $q builder context Eloquent rõ
$query->where(fn ($q) => $q->where('status', 1)->orWhere('featured', 1));

// OK — $r resolved value
collect($payments)->filter(fn ($r) => $r->isActive());
```

NGOÀI closure (scope rộng — block 5+ dòng, method param, loop body lớn,
function body) phải có nghĩa:

```php
// BAD
foreach ($products as $p) {                    // $p tồn tại 20 dòng dưới
    if ($p->isAddCart) { ... }
    if ($p->productSpecial) { ... }
}

// GOOD
foreach ($products as $product) {
    if ($product->isAddCart) { ... }
    if ($product->productSpecial) { ... }
}

// BAD
public function handle(Request $r): JsonResponse  // method param
{ ... }

// GOOD
public function handle(Request $request): JsonResponse
{ ... }

// BAD
try { ... } catch (\Throwable $e) {              // dài 10 dòng
    logError($e->getMessage());
    notify($e);
    return errorResponse($e);
}

// GOOD
try { ... } catch (\Throwable $exception) {
    logError($exception->getMessage());
    notify($exception);
    return errorResponse($exception);
}
```

Cutoff đại khái: closure 1-3 dòng dùng tên ngắn cũng được; quá đó hoặc nested
nhiều cấp → đặt tên đầy đủ.

## Seed commands (refactor 2026-06-03)

`SeedProductsCommand` (products:seed):
- Auto-seed taxonomy nếu rỗng: 10 cat cha + 40 cat con + 50 manufacturer
  (`CATEGORY_TREE`, `MANUFACTURERS` const). Flag `--no-taxonomy` để skip khi
  admin có data thật.
- `--truncate` mở rộng cleanup: `product_image, product_filter, product_special,
  product_category, product_description, product, category_description,
  category, manufacturer`.
- Mỗi product: 1 random main image + 2-3 row `product_image` (gallery,
  product_variant_id NULL, type='gallery', is_active=1) từ pool
  `IMAGE_PRODUCTS` 100 entries.
- **MySQL placeholder limit**: 16 cột × 3 ảnh × 2000 products = 96k
  placeholder > 65535 limit. `product_image` insert chunk thành sub-batch
  3000 row mỗi insert (`array_chunk` inline).

`SeedProductVariantsCommand` (variants:seed):
- Flag `--with-custom-fields=N` thêm role custom_field (text/email/phone/
  textarea/radio/select) cho N% product. 6 entry pool `CUSTOM_FIELDS` phủ
  đủ type blade render.
- `upsertOption($name, $type, $role)` — pass `Option::ROLE_VARIANT` hoặc
  `Option::ROLE_CUSTOM_FIELD` explicit.
- Marker `[SEED] *` trong description.name để truncate phân biệt option seed
  khỏi option thật.

## Việc còn nợ — Product detail

- `orders_product_option` cũng có cột `product_option_id` dangling kiểu cũ.
  History data của order — không thể truncate. Audit riêng (Option C trong
  refactor notes): drop `product_option_id`, thêm `option_id`, không FK
  enforce.
- `CartService::289, 422, 438, 456` còn dùng key `product_option_id` trong
  cart serialization. Sửa sang `option_id` sau khi orders_product_option
  được refactor.
- `App\Helpers\Cart` legacy CRASH sau migration product_option_value cleanup
  (line 282 query `option_value_1_id`). Verify không còn caller trước migrate.
- Variant gallery JS handler chưa được viết — `window.variantGallery` đã
  inject nhưng chưa có listener swap toàn slider khi user chốt variant. Có
  sẵn data structure để mở rộng.
- Admin CMS chưa có UI cho `product_variant.regular_price` — hiện chỉ seed
  generate. Wire `ProductVariantObserver::saved/deleted` recompute
  `product.max_variant_discount_percent` khi xây admin form.
- `setting('config_review_policy', ...)` mâu thuẫn key giữa Service và
  Controller — đã sync ở review nhưng audit lại các flow khác.

## Convention add-to-cart key (refactor 2026-06-05)

Blade `_option.blade.php` emit hai input name khác nhau tuỳ role:

```php
$valueParam = $isVariant ? 'option_value_id' : 'product_option_value_id';
// → option[X][option_value_id]          (variant role)
// → option[X][product_option_value_id]  (custom field role)
```

Hai caller cùng đọc payload PHẢI nhận DIỄN cả 2 key cho variant role (đọc
nhầm `product_option_value_id` của variant = mảng rỗng = silent corruption,
cart add ở product level không biết variant):

- `App\Services\CartService::splitOptionPayload` (line 275) — ưu tiên
  `option_value_id`, fallback `product_option_value_id`.
- `App\Http\Requests\Web\CheckoutAddToCartRequest::withValidator` (line 40)
  — `$hasValueId = !empty($opt['option_value_id']) || !empty($opt['product_option_value_id'])`.

Sync với blade nếu đổi `$valueParam` thì update cả 2 caller. Nếu thêm caller
mới (vd API endpoint), follow pattern.

## Bug fixes add-to-cart flow (2026-06-05)

3 bugs cộng dồn gây 500 hoặc silent corruption trên `/checkout/add-to-cart`:

1. **`CartService::getItems()` (line 133)**: eager-load `'stock'` — relation
   này KHÔNG tồn tại trên `ProductVariant`. Tên đúng là `productStock`.
   Eloquent throw `RelationNotFoundException`. Sửa: `'productStock'`.
2. **`CartService::checkStock()` (line 372)**: `$variant->stock` cùng bug.
   Sửa: `$variant->productStock`. Thêm guard: stock null → return true
   (data drift fallback, đồng bộ `ProductOptionService::buildVariantMatrix`).
3. **Key mismatch variant role** — xem "Convention add-to-cart key" mục trên.

`ZaloPay::__construct` từng đọc `storage/lib/zaloPay/public_key.pem` ngay khi
instantiate. Class này inject vào `CheckoutPaymentService` → inject vào
`CheckoutController` → mọi request checkout instantiate `ZaloPay` → file
thiếu = 500 toàn bộ luồng (kể cả addToCart không hề dùng ZaloPay). Sửa:
lazy-load qua `getPublicKey()`, throw `RuntimeException` chỉ khi
`buildOrderData()` thực sự cần encrypt.

## Shopee-style discount per-variant (refactor 2026-06-05)

Schema cũ chỉ có `product_variant.price` (giá tuyệt đối). Để show struck
price + badge -X% per-variant như Shopee, thêm cột:

- `product_variant.regular_price` DECIMAL(15,2) nullable — giá niêm yết
  (MSRP) per-variant; struck-through ref. NULL = chưa set, fallback
  `product.price`. Migration `2026_06_05_000000`.
- `product.max_variant_discount_percent` TINYINT UNSIGNED nullable —
  denormalized aggregate MAX discount % across variants, dùng cho LIST
  page (Shopee bait: "lên đến -X%"). Migration `2026_06_05_000001`.

### Mô hình giá

```
regular_price (per-variant)  → struck-through (gạch ngang)
price (per-variant)          → current selling
discount % = (regular - price) / regular × 100
```

Hiển thị struck + badge CHỈ khi `price < regular_price`. Variant không sale
(regular = price hoặc NULL) → ẩn struck + badge.

### Flow data

- Service `ProductOptionService::buildVariantMatrix` expose `regular_price`
  + `resolveDefaultVariant` cùng field.
- DTO `ProductDTO::$maxVariantDiscountPercent` (nullable int) đọc từ
  `product.max_variant_discount_percent`.
- Blade `web.product.index`:
  - Inject `productBasePrice = $entity->price` xuống JS (fallback ref khi
    `variant.regular_price` null).
  - Render struck `#price-product-old` + current `#price-product` + badge
    `#discount-badge` với initial value từ `defaultVariant.regular_price`.
- JS `style.js updateDiscountBadge(variant)`:
  - Ưu tiên `variant.regular_price`, fallback `productBasePrice`.
  - `% = round((ref - price) / ref × 100)`.
  - Show/hide struck + badge dynamically khi user pick variant.
  - Gọi từ `applyVariant(variant)`.
- Blade list `_product.blade.php`:
  - Ưu tiên `$product->maxVariantDiscountPercent` khi hasVariants.
  - Fallback `$special->discountPercent` cho product không variant.

### Seed

`SeedProductVariantsCommand`:
- `regular_price = price × random(110-150%)` cho 80% variant, `= price`
  cho 20% còn lại (test case không sale).
- `backfillProductAggregates` thêm UPDATE statement compute
  `max_variant_discount_percent` bằng `MAX(FLOOR((regular - price)/regular*100))
  GROUP BY product_id`.
- Truncate flow reset `max_variant_discount_percent = null`.

### Việc còn nợ — discount

- Observer `ProductVariantObserver::saved/deleted` cần recompute
  `max_variant_discount_percent` khi admin update. Hiện chỉ seed compute.
- Admin CMS form variant cần thêm field `regular_price`.

## Cluster variant special (refactor 2026-06-06)

Lý do tách bảng (Hướng B): `variant.regular_price` chỉ MSRP tĩnh để gạch
ngang, không tả được time-bound + user_group + priority — đó là việc của
`product_special`, nhưng applying product_special cho variant nghĩa là giảm
% trên tất cả variants (vô nghĩa khi variant đã có giá tuyệt đối riêng).
Giải: cluster mirror ở variant level.

### Schema

```
product_variant_special (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_variant_id BIGINT UNSIGNED FK CASCADE,
    product_id INT FK CASCADE,                  -- denormalize cho backfill aggregate
    user_group_id INT UNSIGNED DEFAULT 1,
    priority INT DEFAULT 0,
    price DECIMAL(15,2) NOT NULL,               -- tuyệt đối, KHÔNG phải delta
    date_start DATETIME NULL,
    date_end DATETIME NULL,
    timestamps + deleted_at
)
INDEX idx_pvs_lookup (product_variant_id, user_group_id, priority, date_start, date_end)
INDEX idx_pvs_product (product_id, user_group_id)
```

Migration `2026_06_06_000000_create_product_variant_special_table.php`.

### Mô hình giá variant (3 mảnh)

```
regular_price (variant)           → MSRP tĩnh, gạch ngang khi đang sale
price (variant)                   → giá bán thường (no campaign)
variant_special.price (campaign)  → override variant.price trong date range

effective_price = COALESCE(variant_special.price, variant.price)
strike_price    = regular_price (nếu > effective)
                  hoặc variant.price (nếu special active và > effective)
                  hoặc null (không có gì để strike)
```

Hai mức discount có thể xếp chồng: regular 1.500k → variant.price 1.200k →
variant_special.price 800k → frontend show "800k" với "1.500k" gạch ngang
và badge -47%.

### Flow data

- Service `ProductOptionService::resolveVariantPricing(ProductVariant)`
  — gộp 1 chỗ logic compute `[effective_price, strike_price, special]`.
  Dùng chung `buildVariantMatrix` + `resolveDefaultVariant` → không drift.
- Service expose thêm 3 field per variant: `effective_price`, `strike_price`,
  `special` (array {id, price, date_start, date_end} hoặc null cho countdown).
- DTO `ProductDTO::formatPrice` → `resolveVariantRange`:
  - Detail page (relation `productVariants.productVariantSpecial` đã load):
    tính MIN/MAX effective per-variant trong PHP.
  - List page card (KHÔNG load specials): fallback `min/max_variant_price`
    denormalized (giá base, KHÔNG reflect productVariantSpecial). Trade-off
    documented — card price hơi lệch khi đang chạy campaign, detail nhìn
    vào sẽ đúng.
- Repository `ProductRepository::detailRelations()` eager-load thêm
  `productVariants.productVariantSpecial`.
- Cart `CartService::resolvePrice(Product, ?Variant)`:
  - Có variant: `COALESCE(productVariantSpecial, variant.price)`. KHÔNG đụng
    productSpecial cho variant.
  - Không variant: `COALESCE(productSpecial, product.price)`.
- JS `style.js updateDiscountBadge(variant)`:
  - `currentPrice = variant.effective_price ?? variant.price` (backward compat).
  - `refPrice = variant.strike_price ?? legacy_calc_from_regular_price`.
  - Show struck + badge khi current < ref.
- JS `applyVariant(variant)`:
  - `#price-product.html(formatPriceLabel(variant.effective_price))`.

### SQL filter/sort (`Product::effectivePriceExpression`)

Bindings: 6 placeholders. Thứ tự `[groupId, now, now, groupId, now, now]`:
3 đầu cho `variant_special` subquery, 3 sau cho `product_special`. MySQL
bind tất cả `?` trước khi CASE evaluate — KHÔNG short-circuit theo nhánh.

```sql
CASE
  WHEN product.has_variants = 1 AND product.min_variant_price IS NOT NULL THEN
    (SELECT MIN/MAX(COALESCE(
        (SELECT pvs.price FROM product_variant_special pvs
         WHERE pvs.product_variant_id = pv.id
           AND pvs.user_group_id = ?
           AND (pvs.date_start IS NULL OR pvs.date_start <= ?)
           AND (pvs.date_end   IS NULL OR pvs.date_end   >  ?)
           AND pvs.deleted_at IS NULL
         ORDER BY pvs.priority DESC LIMIT 1),
        pv.price))
     FROM product_variant pv
     WHERE pv.product_id = product.id AND pv.deleted_at IS NULL)
  ELSE COALESCE(
    (SELECT ps.price FROM product_special ps
     WHERE ps.product_id = product.id
       AND ps.user_group_id = ?
       AND (ps.date_start IS NULL OR ps.date_start <= ?)
       AND (ps.date_end   IS NULL OR ps.date_end   >= ?)
     ORDER BY ps.priority DESC LIMIT 1),
    product.price)
END
```

Nhánh `variant_special` theo scope `dateStartToEnd` (`<=` start, `>` end strict).
Nhánh `product_special` giữ `<=` / `>=` theo implementation cũ — drift đã
documented, sửa kèm task unify riêng để tránh scope creep.

### Việc còn nợ — variant special

- Observer `ProductVariantSpecial::saved/deleted` chưa có. Cần recompute
  `min_effective_variant_price` / `max_effective_variant_price` (cột mới sẽ
  thêm) khi campaign start/end. Trước khi có cột này, list page card vẫn
  show base range — chấp nhận trade-off.
- Schedule job daily ban đầu/cuối campaign để invalidate cache `products:{id}`.
  Hiện cache chỉ invalidate qua observer Product/Variant save.
- Admin CMS form `product_variant_special` chưa có UI. Seed command cũng
  chưa support — bổ sung khi dùng thật.
- DTO `VariantSpecialDTO` nếu cần expose qua API. Hiện chỉ embed vào matrix
  như array.
- Drift toán tử `date_end` giữa nhánh variant (`>` strict) và nhánh simple
  (`>=`) trong effectivePriceExpression — chọn 1 và sync khi unify drift
  `dateStartToEnd` toàn project.

## Data drift safety nets — variant matrix (2026-06-05)

`ProductOptionService::buildVariantMatrix` fallback khi `$variant->productStock`
null (data drift, eager-load fail, hoặc stock chưa tạo):

```php
$available = $stock ? (int) $stock->available : 999;
$subtract  = $stock ? (bool) $stock->subtract : false;
$has_stock = $stock !== null;
```

**TRƯỚC**: fallback `(0, true)` — coi như OOS. UI grey TẤT swatch ngay init
khi seed/import data drift → user tưởng product hỏng.

**SAU**: fallback `(999, false)` — coi như "không track stock", variant
pickable. Stock thật ép tại `OrderService` khi tạo order. Pattern: UI
optimistic, validation tại checkout. Field `has_stock` expose để JS detect.

JS `style.js refreshAvailability`:
- Đã chuyển sang **Shopee classic**: init = ALL enable, chỉ disable sau
  khi user pick value đầu (`!hasSelection || optionAlreadySelected ||
  isValueAvailable`). Bỏ "dead-end protection từ init" — gây false positive
  khi data drift.
- **`ALL_OOS` safety net** — detect mọi variant `subtract=true + available=0`
  → bỏ qua OOS check, log warning console. Bảo vệ UX khi DB data sai.
- `applyVariant()`: cũng respect `ALL_OOS` → button mua hàng không bị ẩn.
- Button toggle guard: chỉ toggle khi cả `#button-cart` và `#button-contact`
  cùng tồn tại (blade có thể chỉ render 1 button).

JS debug helper:
```js
window.dumpVariants()  // console.table matrix + count rows có stock
```

## Image gallery slick — manual sync (refactor 2026-06-05)

Slick `asNavFor` + `focusOnSelect` gây auto-scroll strip khi click/slickGoTo
(= "nhảy từng cái"). Hover handler dùng src-mutation tránh điều này, nhưng
click vẫn nhảy.

Giải pháp: **BỎ `asNavFor` + `focusOnSelect`**, handle sync manual.

```js
$('.product-image-slider').slick({
    slidesToShow: 1, fade: true, speed: 0,
    // không asNavFor
});
$('.slider-nav-thumbnails').slick({
    slidesToShow: 4, speed: 0,
    // không asNavFor, không focusOnSelect
});

// Click thumb → slickGoTo main + manually toggle .slick-current trên strip
$(document).on('click', '.slider-nav-thumbnails .slick-slide:not(.slick-cloned)', function () {
    var idx = parseInt($(this).attr('data-slick-index'), 10);
    $strip.find('.slick-slide').removeClass('slick-current slick-active');
    $(this).addClass('slick-current slick-active');
    $('.product-image-slider').slick('slickGoTo', idx);
});
```

**Hover preview** (mutate src + class indicator):
- mouseenter thumb → mutate `.slick-active img` src + add `.is-hover-active`.
- mouseleave strip → KHÔNG dọn class, KHÔNG restore src. Thumb cuối giữ
  `.is-hover-active` → CSS suppress `.slick-current` thật, hiện hover thumb
  như "selected". Ảnh main đã match.
- click → dọn cờ hover, slick commit `.slick-current` thật.

CSS cần (`custom.css` 261/265/279):
- `.is-hover-active` styled giống `.slick-current` (border + triangle).
- `.is-hovering .slick-current:not(.is-hover-active)` → border transparent
  (suppress real current khi đang hover).

Trade-off: slick internal `currentSlide` không sync với hover state.
Acceptable vì click commit chuẩn, arrow next/prev hiếm dùng cho gallery.

## Cluster review Shopee-style (refactor 2026-06-04 → 2026-06-10)

Refactor toàn diện bảng `review` legacy (id/product_id/user_id/ip/author/text/
rating/email/is_publish) thành cluster 8 bảng đáp ứng Shopee UX: đa tiêu chí,
media (ảnh + video), shop reply, helpful vote, tag, report, verified purchase.

### Schema (migration `2026_06_04_000000` → `_000009`)

```
review (refactor)
├─ +order_id, +product_variant_id, +title, +status (tinyint), +is_anonymous
├─ +language_code, +helpful_count, +unhelpful_count, +reply_count, +media_count
├─ +edit_count, +last_edited_at, +approved_at, +approved_by, +source, +user_agent
├─ Status workflow thay is_publish: 0=pending 1=approved 2=rejected 3=hidden
│  (is_publish vẫn giữ — backward compat; sync với status trong migration).
├─ UNIQUE (order_id, product_id) — 1 order × 1 product = 1 review.
└─ INDEX (product_id, status, helpful_count, deleted_at) — covering cho sort
   default `-helpful_count` (migration `2026_06_10_000000`).

review_criteria + review_criteria_description (i18n)
└─ Seed 5 tiêu chí: quality / description_match / service / packaging / shipping
   (mặc định active, vi+en). Admin có thể tắt is_active, KHÔNG xóa cứng (review_rating
   FK RESTRICT).

review_rating (pivot composite PK)
└─ (review_id, review_criteria_id) → 1 review × 1 criteria = 1 rating. CASCADE
   delete review, RESTRICT delete criteria.

review_media (gộp ảnh + video — discriminator `type`)
└─ Quota Shopee: 9 ảnh + 1 video / review (enforce ở Service, không phải DB).

review_reply (self-ref `parent_reply_id`)
└─ author_type: 0=customer, 1=shop, 2=admin. Depth max 2 (enforce app layer).

review_helpful (UNIQUE review_id + user_id)
└─ vote_type: 1=helpful, -1=unhelpful, 0=withdrawn. Visitor dùng user_id=0
   + ip (chống spam yếu — middleware rate limit là chính).

review_tag + _description + _pivot (3 bảng)
└─ Seed 8 tag preset (great_quality, as_described, fast_delivery, …).
   usage_count denormalize cập nhật qua observer attach/detach pivot.

review_report
└─ UNIQUE (review_id, reported_by). reason_code: spam/offensive/fake/
   irrelevant/other. Admin queue qua status pending/resolved/rejected.

product (aggregate cache thêm 5 cột)
└─ review_count, rating_avg(decimal 3,2), rating_sum, rating_distribution(JSON
   {"1":n,..,"5":n}), rating_updated_at. Observer incremental UPDATE thay vì
   COUNT/AVG full mỗi lần.
```

### Convention chốt

**FK type** — review.id INT signed (legacy AUTO_INCREMENT), product_variant_id
BIGINT unsigned (cluster mới); pivot child dùng `integer('review_id')` (INT) +
`unsignedBigInteger('review_criteria_id')`.

**Cache tag** lưu ở `config/core/config.php → cache.review` (convention dự án
"mọi cache key/tag đều nằm trong core config", KHÔNG hardcode literal trong
repo):

- `cache.review.tag_root` → `'review_root'` — flush mọi review-related cache.
- `cache.review.tag_criteria` → `'review_criteria'` — getActiveCriteria.
- `cache.review.tag_tag` → `'review_tag'` — getActiveTags.
- `cache.review.tag_product` → `'reviews:'` (prefix) — caller concat productId
  để có tag per-product `reviews:{productId}`. Observer
  `forgetCacheTagged([getCoreConfig('cache.review.tag_product').$productId])`
  khi review save/update — auto invalidate getCriteriaAverages + listForProduct
  first-page cache.
- `cache.review.key_criteria_active` / `key_tag_top` / `key_criteria_avg` —
  cache key prefix tương ứng cho 3 method trên.

**FK column naming** đầy đủ prefix tên bảng (`review_criteria_id`,
`review_tag_id`, `product_variant_id` — KHÔNG `criteria_id`/`variant_id`).

### Config + i18n

`config/core/config.php` thêm block `review`:
- `review.status` — pending(0)/approved(1)/rejected(2)/hidden(3). KHÔNG dùng
  const trong Model, mọi nơi đọc qua `getCoreConfig('review.status.approved')`.
- `review.policy` — public/login/purchase (3 mức ai được review).
- `review.default_policy` = 'public' (default fallback).

`review_report.status` — pending(0)/resolved(1)/rejected(2) tách block riêng.

**Policy admin cấu hình qua DB**: key `setting('config_review_policy', ...)`.
Service + Controller cùng đọc key này (tránh drift — bug chốt trong commit
2026-06-08).

`lang/vi/messages.php` block `review.*`:
- `login_required`, `verified_purchase_required`, `already_submitted` — Service throw.
- `review.save.{product_required,text_required,text_min,…}` — FormRequest messages.
- `review.{review_id_required,review_not_found,vote_invalid,reason_required,…}` — common.

KHÔNG hardcode tiếng Việt trong PHP code — luôn `trans('messages.review.*')`.

### Tầng kiến trúc (Controller → Service → Repository → Model)

```
ReviewController (Web)
├─ saveReview(ReviewSaveRequest)
│  └─ gate `$this->productRepo->findReviewableProduct($id)` (lazyMap, không
│     inject) — chặn product is_review=0.
│  └─ ReviewService::submitReview(...)
├─ vote(ReviewVoteRequest) auth — ReviewService::vote(...)
├─ report(ReviewReportRequest) auth — ReviewService::report(...)
└─ list($productId) — KHÔNG fetch product. Spatie filter/sort + paginator
   wrap items thành ReviewDTO. Trả view _comment_list partial (HTML).

ReviewService
├─ submitReview() — orchestrate transaction 4 bảng:
│   1. Verified purchase check (reviewRepo->findVerifiedOrderId)
│   2. Policy gate (setting('config_review_policy', ...))
│   3. Chống trùng (UNIQUE constraint DB + check trước cho UX message)
│   4. Insert review root + review_rating + review_media + review_tag_pivot
│   5. Throw \DomainException với trans() message khi vi phạm rule.
├─ vote() — lockForUpdate review + upsert review_helpful + diff counter
│   denormalize trên review row (helpful_count/unhelpful_count).
├─ report() — updateOrCreate(review_id + reported_by) chống spam.
└─ KHÔNG inject ProductRepositoryInterface — gate is_review đặt ở Controller
   qua lazyMap auto-resolve.

ReviewRepository (extends QueryableRepository + CacheableRepository)
├─ allowedFilters: rating, has_media, has_text, tag (whereHas).
├─ allowedSorts: `review.helpful_count`, `review.created_at`, `review.rating`
│   (prefix `review.` để disambiguate JOIN — Spatie tự thêm WHERE table = `review`).
│   defaultSort `-review.helpful_count`.
├─ listForProduct() — closure modifier append `->forProduct($productId)` vào
│   baseQuery().
├─ withRelations() conditional: 'helpfuls' chỉ load khi user logged-in (filter
│   user_id để DTO compute myVote không N+1).
├─ getActiveCriteria/Tags — cached tag review_criteria/review_tag (1day/1h).
├─ getCriteriaAverages($productId) — JOIN review_rating × review × criteria
│   GROUP BY code. Cache tag review_root + reviews:{productId}.
├─ findVerifiedOrderId() / hasReviewedFromOrder() — check ở orders_product.
└─ forgetProductCache() — observer gọi sau save/delete.

ReviewObserver
├─ Trigger trên Review (đăng ký AppServiceProvider::boot).
├─ created/updated/deleted — chỉ áp dụng delta khi status APPROVED.
├─ applyDelta() — incremental UPDATE product (review_count, rating_sum,
│   rating_avg, rating_distribution JSON_SET). KHÔNG re-aggregate full.
└─ invalidate() — forgetProductCache mỗi delta. Try/catch swallow exception
   (cache fail không phá save flow).

ReviewDTO + 5 DTO con
├─ ReviewDTO::fromModel(Review $r, ?int $currentUserId) — `myVote` đọc từ
│   relation helpfuls đã eager-load filtered user_id.
├─ ReviewCriteriaDTO / ReviewTagDTO / ReviewRatingDTO / ReviewMediaDTO /
│   ReviewReplyDTO — Spatie Data v4, camelCase property.
└─ Collection con DTO: `Illuminate\Support\Collection` + `#[DataCollectionOf]`
   per CLAUDE.md convention.
```

### Routes (group prefix `/review`)

```
POST /review              review.saveReview  (public, gate Service)
GET  /review/list/{id}    review.list        (AJAX partial)
POST /review/vote         review.vote        (auth)
POST /review/report       review.report      (auth)
```

Frontend gọi qua `routeArea('review.xxx')` — ExtendedRoute prefix `web.`
auto-resolve.

### Frontend AJAX (KHÔNG append URL product)

Section `#review-section` chứa data-attr:
`data-list-url`, `data-save-url`, `data-vote-url`, `data-report-url`,
`data-csrf`, `data-product-id`. JS đọc từ đây — blade không generate URL lặp.

State local in-memory (không sync URL/history):
```js
state = { filter: {rating, has_media, has_text, tag:[]}, sort, page }
```

7 partial trong `resources/views/web/product/structure/`:
- `comment.blade.php` — entry point, @php resolve + 7 @include.
- `_comment_summary.blade.php` — avg rating + distribution + criteria breakdown.
- `_comment_filter.blade.php` — chips + sort dropdown (data-review-filter).
- `_comment_list.blade.php` — review item loop, tự include _paging cuối.
- `_comment_form.blade.php` — write form đa tiêu chí, dispatch UI theo policy.
- `_comment_report.blade.php` — modal Bootstrap 4.
- `_comment_styles.blade.php` — CSS scoped Shopee palette + skeleton + rating-fractional.
- `_comment_script.blade.php` — IIFE module, IntersectionObserver lazy load.

### Lazy load tối ưu (2026-06-10)

`ProductController::index` KHÔNG gọi `listForProduct()` server-side (eager-load
6 relation lồng × 500k+ row → slow first-load). Skeleton placeholder render
ngay; JS `reload()` chạy khi IntersectionObserver detect `#review-section`
gần viewport (rootMargin 300px) hoặc setTimeout 1.5s fallback.

Giữ SSR (cheap + cached):
- criteria, tags (1day/1h cache)
- criteriaAverages (1h cache trên product_id)
- hasReviewed, hasVerifiedPurchase (single-row index lookup)
- reviewPolicy (setting cache)

Effect: TTFB trang detail ~50-100ms thay vì 500-2000ms.

### Half-star UX (FA 5.0.6 không có fa-star-half-alt)

CSS overlay technique — 2 layer star stack, layer cam clipped theo
`var(--rating-pct)` = rating × 20%. Class `.rating-fractional` + `.rf-bg`
(xám full) + `.rf-fg` (cam overlay với overflow:hidden). Bullet-proof
mọi FA version. Reusable qua CSS variable.

Áp dụng: product header + review summary overview + criteria breakdown grid.

### Variant pricing trong product header

Product có variant: ẨN layout sale (struck/red) vì JS `applyVariant()` ghi
đè `#price-product` SAU page load. Nếu render struck SSR + variant.price >
$entity->price → user thấy "sale > regular" gây hiểu lầm. Blade check
`@if ($entity->hasVariants) → single price` thay vì special layout.

ProductDTO `rating`/`totalRating`/`ratingRounded` ưu tiên cache mới
(`rating_avg`/`review_count`), fallback legacy nếu cache rỗng.

### Seed commands review

**`SeedReviewsCommand` (`reviews:seed`)**:
- Default 100k, chunk=500 (chống OOM).
- Bulk insert AUTO_INCREMENT — đọc lastInsertId() đầu batch, build child
  rows với rid = first+i (KHÔNG explicit id va legacy backfill).
- 2-pass: build $reviewRows + $perRowMeta → insert review → build child.
- Phân phối realistic: 5★ 55% / 4★ 25% / 3★ 12% / 2★ 5% / 1★ 3%.
- Max vote/review = 15. `unset()` + `gc_collect_cycles()` cuối mỗi batch.
- `insertOrIgnore` everywhere cho child (defensive legacy backfill).
- Cuối flow: 2 SQL bulk rebuild — review_tag.usage_count + product aggregate.
- Cron `routes/console.php` hourly: `reviews:seed 500 --chunk=200`.

**`SeedProductVariantsCommand` (`variants:seed`)**:
- Flag `--full` sinh đầy đủ Cartesian Color × Size (tránh dead-end UX
  "Hết hàng" giả khi user pick combo không tồn tại).
- Default random subset (--min=2 --max=3) chỉ dùng stress test.

**JS dead-end protection** (`style.js refreshAvailability`):
- Bỏ short-circuit `!hasSelection || ...` — luôn check `isValueAvailable`
  từ init. Color không có variant in-stock → disable ngay, user không pick
  được dead-end.

### Bug history đã giải

1. Migration `review_*_description.deleted_at` — SoftDeletes nhưng column
   không có → 1054. Fix: gỡ SoftDeletes 2 Description model.
2. Duplicate PK '7-1' review_rating — explicit review.id va legacy backfill.
   Fix: AUTO_INCREMENT + lastInsertId.
3. `config_policy` vs `config_review_policy` drift — Service + Controller
   key khác nhau. Fix: cả 3 tầng dùng `config_review_policy`.
4. Spatie sort `created_at` not allowed — repo prefix `review.*` nhưng blade
   gửi không prefix. Fix: đồng bộ token `review.helpful_count`.
5. URL polluted `?sort=&filter=` ở product page khi filter review. Fix: AJAX
   state local, không touch URL.
6. JS price overwrite "sale > regular" giả khi variant.price > product.price.
   Fix: ẩn struck layout cho product có variant.

### Việc còn nợ — Review cluster

- Admin CMS chưa có UI moderation (duyệt pending, xử lý report queue, ban
  tag, edit criteria). Backend infra sẵn.
- `review_reply` chưa có UI customer nested reply (chỉ shop). Schema sẵn.
- `review_helpful` UNIQUE (review_id, user_id=0) collide cho visitor — chỉ
  1 visitor toàn site có thể vote. Đổi UNIQUE (review_id, user_id, ip)
  hoặc disable vote guest.

## Convention thêm — `routeArea()` cho mọi URL

Mọi URL trong blade dùng `routeArea('name', $params)` thay `route()` — helper
ở Common.php tự prefix area (`web.`/`cms.`/`api.`) theo `getCurrentArea()`.
ExtendedRoute đăng ký route KHÔNG prefix area; gọi qua routeArea mới resolve
đúng. Pattern: auth.login, review.*, checkout.*.

## Convention `lazyMap()` ở base Controller

`App\Http\Controllers\Controller::lazyMap()` định nghĩa repo auto-resolve qua
`__get()` — KHÔNG cần inject explicit ở constructor con. Map hiện: categoryRepo,
zoneRepo, manufacturerRepo, filterRepo, menuRepo, menuValueRepo, productRepo.
Controller con override `lazyMap()` để thêm (vd ProductController thêm
userWishlistRepo).

Truy cập: `$this->productRepo->...`. Base `__get($name)` Container::make
interface mapping, cache singleton trong `$resolved[$name]` per request.

## Local infrastructure — Docker (2026-06-06)

Redis chạy qua `docker-compose.yml` ở root project. Không cài Redis trên
Windows native (không có bản chính chủ stable cho Windows); dùng Docker để
đồng bộ với production AWS (ElastiCache for Redis = managed Redis cluster,
cùng wire protocol).

### Workflow

```
# Lần đầu
docker compose up -d              # start redis + redis-insight ngầm
docker compose ps                 # verify running + healthy

# Hàng ngày
docker compose up -d              # idempotent — start nếu chưa chạy
docker compose stop               # dừng (giữ container + volume)
docker compose down               # xoá container (giữ volume → data còn)
docker compose down -v            # xoá luôn volume — RESET cache + AOF

# Debug
docker compose exec redis redis-cli        # vào CLI: PING, KEYS *, INFO
docker compose logs -f redis               # tail log
http://127.0.0.1:5540                      # Redis Insight UI
```

### Cấu hình

- `redis:7-alpine` — image nhỏ, đủ cho dev.
- `--maxmemory 256mb` + `allkeys-lru`: giới hạn RAM, evict LRU khi đầy.
  Mục đích test data large (50k+ product cache) chạm trần để quan sát
  eviction → đúng case cần thấy ở dev.
- `--appendonly yes`: AOF persistence, restart container không mất cache.
  Production ElastiCache có managed snapshot riêng, flag này chỉ local.
- Database 0 = Laravel session/queue, Database 1 = cache (xem
  `config/database.php` → connection `cache`). Tách DB → `php artisan
  cache:clear` không đụng session.

### Laravel wiring (`.env`)

```
CACHE_STORE=redis
REDIS_CLIENT=predis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=null
REDIS_CACHE_CONNECTION=cache
```

`predis/predis` (pure-PHP) cài qua composer — KHÔNG cần extension. XAMPP
php82 không có sẵn `phpredis` native. Đổi `REDIS_CLIENT=phpredis` khi
production build extension được.

```
composer require predis/predis
php artisan config:clear
php artisan cache:clear
```

### Test connection

```
php artisan tinker
> Cache::store('redis')->put('hello', 'world', 60); Cache::store('redis')->get('hello');
= "world"
> Redis::ping()
= true
```

### Map sang AWS

| Local (docker compose) | AWS equivalent |
|---|---|
| `redis` service | ElastiCache for Redis (Cluster Mode Disabled cho 1 node, hoặc Cluster Mode Enabled cho shard) |
| `redis_data` volume | ElastiCache snapshots + AOF managed |
| `redis-insight` | ElastiCache Console + CloudWatch metrics, hoặc tự deploy Redis Insight container trên ECS |
| `--maxmemory` flag | Tham số `maxmemory-policy` ở ElastiCache parameter group |
| `127.0.0.1:6379` | ElastiCache primary endpoint (TLS port 6380 nếu bật in-transit encryption) |

## Cluster Coupon / Gift / Voucher Shopee-style (2026-06-11 → 2026-06-13)

3 cluster promotion độc lập, mỗi cluster lifecycle riêng. Phân biệt rõ trong
code: KHÔNG được gộp `coupon` và `voucher` cùng namespace dù tên dễ nhầm.

| Cluster | Bản chất | Trừ vào | UI session key |
|---|---|---|---|
| `Coupon` | Mã giảm giá marketing (% / fixed / freeship) | Subtotal / shipping fee | `checkout.applied_coupons` |
| `Gift` | SP tặng kèm khi đơn đủ ĐK (min_subtotal hoặc buy_specific_product) | Không (quà miễn phí) | `checkout.applied_gifts` |
| `Voucher` | Gift card cá nhân (user A tặng B qua email) | VND tuyệt đối từ tổng đơn (sau coupon + ship) | `checkout.applied_vouchers` |

### Session namespace — KHÔNG dùng `cart.*`

**Bug đã trải qua**: session key `cart.applied_coupons` (Laravel dot-notation
lưu thành `session->cart->applied_coupons`) bị **CartService::getItems()**
nuốt và xoá vì loop `session('cart', [])` coi `applied_coupons` như 1 cart
row không có `product_id` → tự gọi `remove()` → `session()->forget('cart.applied_coupons')`.

Tất cả 3 cluster phải dùng **prefix `checkout.*`** (không phải `cart.*`):
- `session('checkout.applied_coupons')` ✅
- `session('checkout.applied_gifts')` ✅
- `session('checkout.applied_vouchers')` ✅
- `CartService::clear()` phải `forget` đầy đủ cả 3 key này khi order success.

### Order of operations trong CheckoutTotalService::build()

Strict ordering — mỗi step compute residual cho step sau:

```
1. lineSubTotal              → subtotal
2. linesAppliedCoupons       → -coupon (percent → fixed → freeship skip)
3. lineGifts                 → info only (value=0, gifts miễn phí)
4. lineReward                → -reward points
5. lineShipping              → +fee | freeship discount | placeholder
6. lineVouchers              → -voucher (cap residual, stack được)
7. lineTotal                 → grand total
```

Voucher BẮT BUỘC áp SAU shipping vì gift card cover được cả phí ship.
Coupon freeship trừ phí ship (qua lineShipping branch); voucher trừ residual
sau shipping.

### Stacking rules

| Cluster | Stack chính nó | Stack với loại khác |
|---|---|---|
| `Coupon` percent | KHÔNG (chỉ 1 winning) | + Coupon freeship (theo config `coupon.stacking.allow_freeship_with_discount`) |
| `Coupon` fixed | KHÔNG | + Coupon freeship |
| `Coupon` freeship | KHÔNG | + 1 percent/fixed |
| `Voucher` | CÓ (stack nhiều, cap residual) | + tất cả coupon + gift |
| `Gift` | N/A (gift là eligibility-based, không stack) | Độc lập |

### Quota / Lifecycle pattern (cluster Coupon + Voucher)

3 status pattern lifecycle giống nhau (lưu trong `*_history.status`):
- `0/1 = applied` — đang ở cart, KHÔNG trừ quota/balance thật.
- `2 = used/confirmed` — order paid → trừ quota / balance.
- `3 = cancelled/refunded` — order cancel → trả về.

Pattern atomic:
- Order create: `INSERT history status=applied`.
- Payment success: `UPDATE status=confirmed` + `DB::increment('used_count')`
  hoặc `DB::increment('redeemed_balance')` (atomic chống race).
- Order cancel: `UPDATE status=cancelled` + `DB::decrement(...)`.

**Voucher khác Coupon ở 1 điểm**: voucher còn flip `voucher.status`
(`active ↔ fully_used`) khi `redeemed_balance >= amount`. Coupon chỉ track
`used_count`.

### Gift cluster bổ sung

Gift KHÔNG có history per-redeem như coupon/voucher — chỉ có `order_gift`
(audit per order). Quota global qua `gift.used_count`.

- `trigger_type=1` (min_subtotal): đọc `gift.min_subtotal`, validate cart ≥ X.
- `trigger_type=2` (buy_specific_product): check `gift_trigger_product` pivot
  intersect cart product IDs. `min_subtotal` bỏ qua.
- `pick_type=0` (auto): tự pre-pick mọi `gift_item`.
- `pick_type=1` (pick_1_of_n): user chọn đúng 1 item (radio).
- `pick_type=2` (pick_up_to_n): user chọn ≤ `pick_limit` items (checkbox).

### UI Pattern (đã chốt cho 3 cluster)

- Mỗi cluster có 2 partial:
  - `_{cluster}_promo_row.blade.php` — clickable row mở modal, hiện chip mã đã áp.
  - `_{cluster}_modal.blade.php` — Alpine `x-data="{cluster}Modal()"`,
    listen `@open-{cluster}-modal.window` / `@remove-{cluster}.window`.
- Container stable ID `#{cluster}-promo-row-container` để JS swap innerHTML
  sau AJAX (xem coupon flow — pattern đầy đủ). Hiện gift + voucher dùng
  reload thay swap DOM — refactor sau nếu UX cần.
- Color coding:
  - Coupon: brand orange (`.text-brand`)
  - Gift: pink (`.text-pink-500`)
  - Voucher: purple (`.text-purple-500`)

### Việc còn nợ phase tiếp

- **Payment callback wire `VoucherService::confirmOrderVouchers($orderId)`**:
  hiện order create chỉ insert `voucher_history` status=applied. Sau ZaloPay
  / COD success cần flip → confirmed + cộng `redeemed_balance`. Chỗ wire:
  `CheckoutPaymentService::processCallback` hoặc `paymentCallBack` action.
- **Coupon `recordApplied` (status=applied khi user check vào cart, chưa paid)**:
  hiện chỉ ghi history khi order tạo. Nếu cần "hold quota" lúc user vào cart
  (chống race khi quota gần hết) — chưa làm.
- **Inline DOM swap cho gift + voucher**: hiện reload trang sau AJAX. Coupon
  đã có pattern swap (`/checkout/coupons/apply` trả `total_data_html` +
  `promo_row_html`).
- **Cron clean stale `coupon_history.status=applied`**: nếu wire phase trên,
  cần job clean rows quá hạn `coupon.cart_applied_ttl_minutes`.
- **Admin CMS UI** cho cả 3 cluster — backend đầy đủ, FE admin chưa có.

## TODO checkpoint — chuyển sang task Login / Account (sau cluster promotion)

Hoàn thành 3 cluster promotion (coupon + gift + voucher) — cluster checkout
flow giờ stable. Bước tiếp theo: **refactor cluster Account / Auth** theo
pattern Shopee/UX hiện đại.

Khu vực cần audit:
- `AuthController` (register, login, social login, forgot password,
  change password, OTP). Đã extends `Controller` mới chưa? Có legacy
  `_buildForSeoBy*` hoặc trait CheckoutMarketing không?
- `AccountController` đã refactor 2026-05-31 — verify chốt convention
  (FormRequest, DTO, service layer) còn áp dụng được không sau khi refactor
  coupon/gift/voucher đụng vào `AccountService::cancelOrder`.
- `User` model: cần thêm scopes / casts cho field social login.
- Phân tầng: tạo `AuthService` (nếu chưa có) tách khỏi controller.
- UI: rewrite login/register blade theo style hiện tại (Tailwind), modal
  reset password, OTP flow.
- Session security: rate limit login, 2FA roadmap.

Xem mục "Account flow (refactor 2026-05-31)" phía trên để hiểu phân tầng
đã setup. AuthController có thể đã legacy hơn (chưa rewrite). Audit trước
khi quyết định scope refactor.

Production: đổi `REDIS_HOST=xxx.cache.amazonaws.com`, set
`REDIS_CLIENT=phpredis` (cài extension trong container PHP-FPM), giữ
nguyên code Laravel.

### Việc còn nợ — infrastructure

- Bổ sung `mysql` service vào docker-compose để chạy hoàn toàn trong
  Docker, bỏ XAMPP. Hiện app vẫn trỏ XAMPP MySQL.
- Bổ sung `php-fpm` + `nginx` service cho parity với production (XAMPP +
  Apache khác stack production).
- Healthcheck cho composer install + migrate ở Dockerfile khi PR
  containerize hoàn toàn.

## Cluster `product_stock` — bán khống + warehouse single source

Refactor 2026-06-11 thống nhất nguồn tồn kho cho mọi product (simple lẫn
variant) qua `product_stock`. Đường đi hiện tại:

```
Product ── defaultVariant (hasOne ofMany is_default) ── productStock (hasOne)
        └─ productVariants (hasMany) ────────────────── productStock (hasOne / variant)
```

Mọi simple product đã được migration `2026_06_11_000003_unify_simple_product_stock`
tạo cho 1 `product_variant` mặc định (is_default=1, không attribute) + 1
`product_stock` (on_hand = product.quantity tại thời điểm chạy).

### Schema bổ sung

`product_stock.inventory_policy TINYINT UNSIGNED DEFAULT 0`:

| Giá trị | Tên       | Hành vi cart / order                                       |
|---------|-----------|-------------------------------------------------------------|
| `0`     | deny      | Block sale khi `on_hand - reserved <= 0` (default).         |
| `1`     | backorder | Cho phép sale, `on_hand` có thể âm = backlog ("bán khống"). |
| `2`     | untracked | Không trừ tồn, luôn sellable (digital / dropship).          |

Backfill từ `product_stock.subtract`: `subtract=1 → deny`, `subtract=0 → untracked`.
Convention: **không hard-code 0/1/2**, mọi caller đọc qua
`getCoreConfig('stock.policy.deny|backorder|untracked')`.

### Config tập trung — `config/core/config.php → stock`

```php
'stock' => [
    'policy' => [
        'deny' => 0, 'backorder' => 1, 'untracked' => 2,
    ],
    'movement_type' => [
        'receive', 'sale', 'sale_backorder', 'reserve', 'release', 'adjust', 'transfer',
    ],
    'default_warehouse_id' => 1,
],
```

Mọi nơi đọc enum + warehouse_id qua `getCoreConfig('stock.xxx')`, KHÔNG
qua hằng số class. Cùng convention với `option.role`, `coupon.type`,
`review.status`. Lý do: tránh blade/view "reach into entity" để hỏi enum,
đổi giá trị chỉ sửa 1 chỗ, không drift giữa migration + runtime.

`ProductStock` model trước có `const POLICY_DENY|BACKORDER|UNTRACKED` và
`DEFAULT_WAREHOUSE_ID` — đã gỡ. Migration `2026_06_11_000003` giữ local
const để self-contained (run-once snapshot, không depend runtime config) —
đó là ngoại lệ duy nhất.

### Method explicit thay accessor magic

`ProductStock::getAvailableAttribute()` từng dùng accessor `$model->available`
nhưng KHÔNG chạy ổn định trên stack Base + Compoships + Laravel 12 —
`$this->available` rơi vào fallback raw column / null thay vì gọi accessor.
Đã thay bằng method thường:

```php
public function sellableQuantity(): int     // max(0, on_hand - reserved)
public function canSell(int $qty): bool     // honour policy + sellableQuantity()
public function tracksMovements(): bool     // false khi policy = untracked
```

**Convention chung**: khi expose computed value trên model, ưu tiên method
thường (`$stock->sellableQuantity()`) thay vì `getXxxAttribute`. Lý do:
trait `Awobaz\Compoships` + Base override `castAttribute` + Laravel 12
dual Attribute API tạo nhiều đường resolve attribute — method thường
tránh hết magic, gọi nào ăn nấy.

### Flow add-to-cart strict-no-legacy

`CartService::tryAdd(payload)` — entry point validate-then-persist:

1. `resolveVariantId(productId, attrs)` — simple product không có attr →
   fallback `resolveDefaultVariantId(productId)` (qua `Product::defaultVariant`
   relation). Cart line LUÔN mang `product_variant_id` post-unify.
2. `makeKey(productId, variantId, customOptions)` + đọc `alreadyInCart`
   trong session cho cùng key.
3. `checkStock(product, variant, alreadyInCart + requested)`:
   - `$stock = $variant?->productStock ?? $product->defaultVariant?->productStock`.
   - Không phải `ProductStock` instance → return `false` (strict deny).
     **KHÔNG fallback** sang `product.quantity / product.subtract`.
   - Có → `$stock->canSell(totalAfter)`.
4. `resolveAvailable(product, variant)` — đồng nhất, cùng `$stock` lookup.
   Trả `PHP_INT_MAX` cho `untracked/backorder`, `0` cho missing stock.
5. Persist qua `persistLine()` (chia sẻ với `add()` legacy entry).

`CheckoutController::addToCart` gọi `tryAdd`, trả `errValidator` với
message giàu ngữ cảnh khi `ok=false`: "Sản phẩm X chỉ còn N trong kho,
bạn yêu cầu M" / "bạn đã có K trong giỏ, yêu cầu thêm Q (tổng T) nhưng
kho chỉ còn N". Cũ "chỉ còn 30 trong kho" không tiết lộ M nên user
tưởng "30 = đủ".

### `CreateOrderService::subtractStock` — strict + audit

1. `variant_id` null → `logError(...)` + return (không silent bump
   `product.quantity` nữa).
2. Lookup `product_stock` với `lockForUpdate()` để chống TOCTOU.
3. Policy gating:
   - `untracked` → no-op.
   - `deny` → decrement (CartService đã gate sẵn, lock chống concurrency).
   - `backorder` → decrement, cho `on_hand` âm. `stock_movement.type =
     sale_backorder` để admin filter ra backlog.
4. `version` tăng 1 mỗi UPDATE → optimistic lock infrastructure sẵn sàng.
5. Append `stock_movement` cho mọi tracked sale → rebuild on_hand được từ log.

### `ProductDTO` aggregation across variants

- `formatStock(Product)`: variant product duyệt `productVariants`, lấy
  label của variant đầu tiên `canSell(1)`. Fallback `defaultVariant` cho
  simple. Không còn nhánh `product.quantity`.
- `resolveInStock(Product)`: boolean aggregator — true nếu BẤT KỲ variant
  còn sellable, hoặc defaultVariant sellable (cho simple).
- Blade chi tiết product gate button "Mua hàng" / "Liên hệ mua hàng" qua
  `$entity->inStock` (KHÔNG `$entity->quantity > 0` nữa — quantity của
  parent variant product luôn 0).
- `stockLabelFromPolicy(Product, ProductStock)`: helper decision tree
  untracked → instock label / backorder + available=0 → "Đặt trước -
  giao sau" / available=0 + deny → stockStatus name / else → count hoặc
  instock label.

### Filter `in_stock` ở list page

`ProductRepository::allowedFilters('in_stock')`: dùng `EXISTS` subquery
qua `product_variant + product_stock`:

```sql
EXISTS (
    SELECT 1 FROM product_variant pv
    JOIN product_stock ps ON ps.product_variant_id = pv.id
    WHERE pv.product_id = product.id
      AND pv.deleted_at IS NULL
      AND (
        ps.inventory_policy IN (backorder, untracked)
        OR (ps.on_hand - ps.reserved) > 0
      )
)
```

`= '1'` → `whereExists`, `= '0'` → `whereNotExists`. Hoạt động cho cả
simple + variant qua cùng predicate. Mọi giá trị enum đọc qua
`getCoreConfig('stock.policy.xxx')`.

### `ProductOptionService::buildVariantMatrix` — UI affordance

Khi `productStock` null (data drift / seed thiếu): trả `available = 999`,
`subtract = false` (= "untracked" về UX) cho swatch vẫn pickable. **KHÁC**
với `CartService::checkStock` strict — đây là affordance cho detail page,
gate thật chạy lúc add-to-cart. Document có chủ ý, không nhầm với
"missing stock = OOS" của cart pipeline.

### Việc còn nợ — stock cluster

- Drop `product.quantity` + `product.subtract` cột legacy sau khi observe
  1-2 sprint không còn caller (search `->quantity` / `->subtract` ngoài
  DTO/seed phải = 0).
- `SeedProductsCommand` chưa tạo default variant + stock cho product mới
  → newly seeded product rơi vào strict-deny đến khi chạy lại unify
  migration. Patch seed command tạo cả cluster.
- Observer `ProductVariantSpecial::saved/deleted` chưa có để recompute
  `min_effective_variant_price / max_effective_variant_price` (cột mới
  sẽ thêm). Hiện list page card vẫn show base range, không reflect
  campaign — trade-off documented.
- Reservation pattern (`stock_reservation` table + TTL sweeper) — pattern
  Magento/Shopee chống oversell concurrent thực sự ở Black Friday. Cluster
  hiện đã có cột `reserved` nhưng chưa có flow nào ghi. Roadmap.
- Admin CMS chưa có UI chọn `inventory_policy` per variant + warehouse
  picker khi nhập kho. Backend ready.
- Dashboard "Backorder backlog" — `WHERE stock_movement.type =
  sale_backorder` group by variant — admin xem cần nhập bù bao nhiêu.

## Cache key cho entity composite-PK — `rememberEntity()`

`CacheableRepository::rememberEntity($entity, $prefix, $resolver, $ttl,
$tags, $perLocale)` — helper canonical để cache "1 row entity" qua
`HasCompositeKey::getKeyAsString()`:

- Single-PK model → suffix là `5` (PK value).
- Composite-PK model (description, pivot) → suffix `5k_kvi`
  (`<id>k_k<lang>` từ trait).

Lợi ích so với hand-roll `"prefix_{$id}_{$locale}"`:
- Schema-agnostic: PK đổi shape (thêm/bớt cột) thì cache key tự khớp,
  không cần update từng caller.
- Single source of truth cho separator (`k_k` từ trait), không drift
  giữa `_` / `:` / `|` giữa các caller.
- `perLocale` auto-resolve: composite-PK đã có `language_code` trong
  suffix → mặc định `false` (không double-append). Single-PK locale-sensitive
  → mặc định `true`. Caller có thể force.

Usage:

```php
return $this->rememberEntity(
    $description,
    getCoreConfig('cache.category_desc'),
    fn () => $this->buildPayload($description),
    ttl: now()->addHours(6),
    tags: [getCoreConfig('cache.category_root')],
);
```

**Convention**: khi cần cache 1 row entity (description, pivot, single
row product...), DÙNG `rememberEntity` thay vì manual concat key. Để
`rememberCache` / `rememberCacheTagged` cho cache list/aggregate có key
do business compose (vd `cache.product_latest_{groupId}_{type}_{limit}`).

## Audit Base traits — Cần review trước khi action (2026-06-11)

Audit `Base.php` use list để rà cruft. 3 finding mở, **chưa action**,
cần đọc kỹ trước khi quyết:

### `HasSchemaCache` — SỐNG NGẦM, KHÔNG xoá

- Public API thấy được: `getTableColumnAndTypeList()` — chỉ 1 caller
  (`ProductSpecialRepository::__construct`, validate sort_field).
- **Bí mật**: override `getFillable()` — khi model không khai báo
  `$fillable` thì return `array_keys(schema)`. **135/136 model entity
  KHÔNG khai báo `$fillable`** → trait đang silent là single-source
  of-fillable cho gần toàn bộ codebase.
- Xoá trait = vỡ mass assignment (`create()`/`update()`/`fill()`) của
  135 model. Phải migrate từng model bổ sung `$fillable` thủ công
  trước → task lớn, không phải 1 PR.
- **Verdict**: GIỮ. Cần thêm warning docblock "Single source of
  fillable cho 135 model, không xoá."

### `HasAuditColumns` — DEAD infrastructure

- Boot listener `creating`/`updating` trên mọi model, nhưng gate qua
  `$hasActionBy = false` (default) → return không ghi gì.
- Grep toàn repo: **KHÔNG model nào** set `$hasActionBy = true` hoặc
  gọi `setHasActionBy(true)`. Tức `fillUpdatedBy()` / `fillDeletedBy()`
  / `setDeletedAt()` đều dead path.
- Trùng `OwenIt\Auditing\Auditable` (3rd party, đã use ở `Product`) —
  log audit đầy đủ vào bảng `audits`. Đó mới là audit thật.
- Native Eloquent `$timestamps = true` + `SoftDeletes` đã cover
  `created_at`/`updated_at`/`deleted_at`.
- **Verdict đề xuất**: GỠ trait + bỏ `use HasAuditColumns` ở Base. 0
  hành vi đổi (gate đã false sẵn), giảm 1 boot listener mỗi model.
- **Cần xác nhận**: chắc không có module admin tương lai nào dự định
  bật `$hasActionBy = true` mà chưa code → nếu có thì giữ làm opt-in
  infrastructure, document cách bật.

### Lỗi config — `system.updated_at_column.field` thiếu

`config/system.php` có 4 key `created_by_column / updated_by_column /
deleted_by_column / del_flag_column` nhưng **thiếu `updated_at_column`**.
3 chỗ trong code đọc key đó:

1. `Base::save()` line 104 — `$attrs[null] = now()` → no-op (Eloquent
   đã handle `updated_at` qua `$timestamps = true`, dòng này thừa).
2. `HasSchemaCache::getFillable()` line 31-34 — gate `if ($updatedAt)`
   nên không vào branch → no harm.
3. `HasCascadeRelations::runCascadeUpdate()` + `cascadeUpdateLeaf()`
   line 128, 136 — `update(['' => $time])` → Laravel ignore key rỗng.
   Đây là path cascade update **thật**: cha update → con cần bump
   `updated_at` nhưng hiện stale.

**Verdict đề xuất**: thêm 1 dòng vào `config/system.php`:

```php
'updated_at_column' => ['field' => 'updated_at', 'comment' => ''],
```

Vừa khớp convention 4 key kia, vừa fix cascade update bị stale ngầm.
Dòng `Base::save()` line 104 nên gỡ luôn (Eloquent đã làm).

### Trait đã action ở turn này

- `HasUrlAttributes` — **XOÁ** (file + use). Trait dead: gọi `getFileUrl()`
  không tồn tại, chỉ Banner use với `$urlAttributes = []` no-op, lại
  override `getAttribute` (loại magic gây bug `$stock->available`). DTO
  đã cover URL composition.
- `Base::setOriginKeyFromString()` shadow — **XOÁ**. Method dùng
  `data_get` (đọc) thay vì ghi `$this->original`, no-op câm. Trait
  `HasCompositeKey` có version đúng → giờ là implementation duy nhất.

## Hướng B — `product_option` CHỈ custom field, biến thể single source (2026-06-17)

Quyết định chốt: **`product_option` giờ CHỈ chứa option role=custom_field.** Trục
biến thể (variant axes), giá trị chọn được, và metadata option đều suy **trực
tiếp từ `product_variant_attribute`** — một nguồn sự thật duy nhất. Lý do: trước
đó có 2 nguồn rời nhau (khai báo `product_option` role=variant vs tổ hợp SKU
`product_variant`) không ràng buộc DB → drift được, phải check 2 vòng. Gộp về 1
nguồn = hết drift, hết check thừa.

- `ProductOptionService::buildVariantOptions` rewrite: gom `$optionMeta`
  (`option_id => Option`, lấy qua `$attr->option`) + `$optionValuesByOption`
  (`option_id => [option_value_id => OptionValue]`, qua `$attr->optionValue`)
  trong 1 vòng lặp variant; sắp trục theo `option.sort_order` rồi `option.id`.
  **KHÔNG còn đọc `product_option`** cho nhánh variant.
- **Hai check trong hàm là HAI CẤP khác nhau, KHÔNG trùng** (đừng gộp/bỏ):
  - `if ($attr->optionValue)` — lọc per-row khi gom (value resolve được mới ghi).
  - `if (empty($optionValues)) continue;` — skip option mà MỌI value đã rỗng.
  Vì `Option` lẫn `OptionValue` đều `SoftDeletes`, `$attr->option` và
  `$attr->optionValue` null độc lập nhau → keyset `$optionMeta` ⊇
  `$optionValuesByOption`. Kịch bản thật: admin soft-delete hết value của 1
  option còn sống → option vào `$optionMeta` nhưng 0 value → không check sẽ
  render picker rỗng ("Màu sắc:" không swatch).
- `required` cho variant **hardcode `true`** — bất biến cấu trúc, không phải
  default: `resolveVariantId` cần đủ mọi trục mới match SKU; và
  `CheckoutAddToCartRequest:34` đã ép `|| $isVariant` bất kể field.
- Eager-load: `ProductRepository::detailRelations()` thêm
  `productVariants.productVariantAttributes.option.description` (metadata trục).
  `CartService::getItems()` cũng thêm relation `option.description` này để
  `buildVariantDisplay` không lazy-load `$attr->option` (N+1 mỗi dòng giỏ).
- Migration `2026_06_16_000000_purge_variant_role_product_option`: xoá row
  `product_option` có `option.role = variant` (`DELETE po JOIN option o WHERE
  o.role = 1`). Idempotent, `down()` no-op (suy lại được từ attribute).
- `SeedProductVariantsCommand` ngừng tạo `product_option` role=variant (bỏ
  plumbing `$productOptions`); `buildBatch` trả 5 phần tử (bỏ productOptions).
- **GIỮ `option.role`** (cột bảng `option`) — vẫn là nguồn phân loại option
  toàn cục: `CartService::splitOptionPayload` + `CheckoutAddToCartRequest` đọc
  `role` để rẽ nhánh payload variant vs custom field. `buildCustomFieldOptions`
  vẫn đọc `product_option` lọc role=custom_field.
- `has_variants` vẫn là **cờ UI-only**; mọi product (kể cả simple) có ≥1
  `product_variant` (default variant từ unify migration 2026_06_11), simple thì
  variant đó KHÔNG có `product_variant_attribute`. `buildVariantOptions` chỉ
  được gọi khi `has_variants=1` (gate ở `buildOptions`).
- Product "có cả custom field LẪN variant" trỏ **song song** 2 bảng:
  variant → `product_variant(_attribute)`, custom field → `product_option`
  role=custom_field. `buildOptions` nối `[variant groups..., custom fields...]`.

## Naming option tree — `selectableValues` / `optionGroups` (2026-06-17)

Đổi tên để key tự-document, không claim sai bảng (Rule "Naming variable" ở trên):

- `buildOptions` trả: `optionGroups` (trước `options`), `variantSwatchImages`
  (trước `imageOptions`), `variantMatrix`, `defaultVariant`, `variantGallery`.
- Mỗi option group: `option_id` (gộp bỏ key `id` trùng giá trị), `type` (gộp
  bỏ `option_type` — thống nhất với cột DB + wire), `name_display`, `role`,
  `required`, `value`, **`selectableValues`** (trước `product_option_values` —
  tên cũ làm tưởng dữ liệu lấy từ bảng `product_option`, sai cho nhánh variant).
  Bỏ key chết `option_name`.
- `_option.blade.php` đọc `$option['option_id']` / `['type']` /
  `['selectableValues']`. `ProductController` map `$optionTree['optionGroups']`
  → view var `productOptions`; `['variantSwatchImages']` merge vào
  `productImages`. JS global `var options` (inject ở `index.blade`) hiện KHÔNG
  được `style.js` đọc (dead) — shape đổi không ảnh hưởng.

## CheckoutContext + CreateOrderService — dọn dead code (2026-06-17)

- `CheckoutContext` giờ chỉ còn `items` / `appliedCoupons` (+`hasFreeshipCoupon`
  /`totalCouponDiscount`) / `orderId` / `userGroupId`. **Bỏ** field `$coupon`/
  `$voucher` + `setCoupon()`/`setVoucher()` — verify toàn repo: 0 caller, field
  luôn rỗng.
- `CreateOrderService`: coupon ghi qua `$ctx->appliedCoupons` trong
  `writeCouponHistory`; voucher ghi qua `writeNewVouchers` →
  `VoucherService::recordOrderVouchers`. **Bỏ** `writeVoucherHistory()` + nhánh
  "Legacy single-coupon fallback" (đều dead vì ctx field luôn rỗng;
  `writeVoucherHistory` còn dead kép: tìm `firstWhere('code','voucher')` nhưng
  voucher line thật mang code `voucher:<CODE>`).
- Cột `orders.coupon` / `orders.voucher` = legacy denormalized, **giờ luôn ghi
  null** (dữ liệu KM thật nằm ở `coupon_history` / `voucher_history`). Nếu muốn
  tra cứu ở cấp order thì cần populate mã đầu tiên — đó là *thêm hành vi*.

## Cart flow — session/key + hiệu năng N+1 (2026-06-17)

- `CartService::clear()` forget thêm `reward`; **bỏ** `forget('coupon')`/
  `forget('voucher')` (key top-level chết, không ai ghi — chỉ còn 1 dòng debug
  blade đọc, đã dọn). 3 cluster KM nhất quán prefix `checkout.*`.
- Item key `'stock'` (boolean stockOk) → **`'in_stock'`** (CartService +
  `cart.blade.php` + `index.blade.php`) — boolean đặt tên như số lượng gây nhầm.
- N+1 đã sửa:
  - `CouponRepository::countUsedByUserForCoupons($userId, $couponIds)` — 1 query
    `GROUP BY` thay `countUsedByUser` mỗi coupon. `CouponService::listForCart`
    pre-fetch map, truyền vào `validateForCart(..., usedByUser:)` (param mới,
    null → fallback query lẻ cho `applyCodes`).
  - `VoucherRepository::findByCodes($codes)` — 1 query keyBy `code` thay
    `findByCode` trong loop. `VoucherService::resolveApplied` dùng (hàm này
    chạy lại mỗi lần build total).
  - `CheckoutCouponController::apply` truyền `$result` vào `renderState`
    (param `$applyResult`) → không chạy `applyCodes` 2 lần; `remove` vẫn tự
    resolve từ session khi không có sẵn.
- `GiftService::getAppliedFromSession()` → `getAppliedGifts()` (nhất quán với
  `VoucherService::getAppliedCodes()`).

## Ghi chú công cụ khi sửa repo này

- **Một số file có byte binary** (vd `CheckoutContext.php`, nhiều `*.blade.php`,
  vài interface) → `grep` báo "binary file matches" hoặc sót dòng, đọc SAI.
  **Tin `Read` tool, KHÔNG tin `grep`** khi verify các file này.
- Sandbox dev **không có php-cli** → không chạy được `php -l` / `artisan
  migrate` / `tinker`. Verify tĩnh bằng Read + grep; chạy thật trên XAMPP. Nhớ
  `php artisan view:clear` khi đổi blade, `cache:clear` khi đổi eager-load (cache
  `getProductDetail` giữ entity với relation cũ).
