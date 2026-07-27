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

> **CẬP NHẬT 2026-06-18 — hợp nhất về `product_variant`** (phần mô tả 2 nhánh
> bên dưới là LỊCH SỬ, đọc để hiểu ngữ cảnh; trạng thái hiện tại là dưới đây):
>
> **Pha 1 — bỏ bảng `product_special`.** Special của simple product giờ nằm ở
> `product_variant_special` của **default variant** (relation `Product::defaultVariant`,
> `hasOne ofMany is_default`). Migration `2026_06_18_000000_merge_product_special_into_variant_special`
> migrate mọi row sang variant_special rồi `DROP TABLE product_special`. Đã xoá
> `ProductSpecial` model + repo + interface; `ProductSpecialDTO` repurpose nhận
> `ProductVariantSpecial` (sau đó linter đổi tên → `ProductVariantSpecialDTO`).
>
> **Pha 2 — bỏ cột `product.price`.** Giá gốc đọc từ default variant qua accessor
> `Product::getPriceAttribute()` → `$this->defaultVariant?->price`. Migration
> `2026_06_18_000001_drop_price_from_product`. Mọi reader `$product->price` giữ
> nguyên (accessor lo); raw SQL KHÔNG dùng accessor → `effectivePriceExpression`
> nhánh simple lấy base từ **subquery giá default variant** (không còn `product.price`).
>
> **`effectivePriceExpression` hiện tại** (cả 2 nhánh từ `product_variant_special`):
>   - Variant: `MIN/MAX(COALESCE(active product_variant_special.price, pv.price))` aggregate qua variant.
>   - Simple: `COALESCE(active special của DEFAULT variant, (SELECT pv.price WHERE is_default=1))`.
>   - Toán tử ngày thống nhất 2 nhánh: `date_start <=`, `date_end >=` (khớp scope `dateStartToEnd`).
>
> **N+1 + accessor — bắt buộc nhớ:**
>   - Accessor `$product->price` đọc `defaultVariant` → **N+1 nếu chưa eager-load
>     `defaultVariant`**. Hot path đã cover (`cardRelations`/`detailRelations` +
>     CartService + UserWishlistRepository đều eager-load `defaultVariant.*`). Code
>     mới đọc giá PHẢI `->with('defaultVariant')`, hoặc cân nhắc thêm `defaultVariant`
>     vào `Product::$with`.
>   - Filter/sort theo giá dùng correlated subquery (1 query, không N+1).
>   - `OrderItemDTO` đọc `$ordersProduct->price` (cột giá lưu lúc đặt) — KHÔNG đụng accessor.
>   - **RỦI RO STACK**: CLAUDE.md đã ghi `getXxxAttribute` có thể KHÔNG fire trên
>     Base+Compoships+Laravel12 (vụ `$stock->available`). PHẢI test `$product->price`
>     trả đúng giá variant TRƯỚC khi chạy migration drop cột; nếu accessor không fire
>     sau drop → giá = 0 toàn site → revert sang đọc `$product->defaultVariant?->price` tường minh.
>
> **Seed:** `products:seed` KHÔNG set price; `variants:seed` tự sinh base
> (`randomBasePrice`) + sinh `product_variant_special` trên default variant qua flag
> `--special-percent=30`.

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
| Cart | `App\Helpers\Cart` đọc `product_option_value`/`_2` legacy | `App\Services\Cart\CartService` resolve `product_variant_id` cluster |
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

## Toán tử ngày "active special" — ĐÃ THỐNG NHẤT (2026-07)

Quy ước DUY NHẤT cho mọi check special/coupon/gift/voucher đang active:
`date_start <= now (hoặc NULL) AND date_end >= now (hoặc NULL)` — 2 đầu inclusive.

Nguồn sự thật: `HasAdvancedScopes::scopeDateStartToEnd`. Nơi dùng:
- `Product::scopeHasActiveSpecial`, `ProductVariant`, `Coupon`, `Gift` → gọi `dateStartToEnd()`.
- `Product::effectivePriceExpression` (raw SQL, 2 nhánh variant + default) → đã đổi `date_end > ?` → `>= ?` khớp scope.
- `ProductVariantAggregateObserver` (raw SQL recompute aggregate) → đã đổi `>` → `>=`.
- Legacy `_buildQueryForProductSpecials` đã gỡ.

Hết drift ở biên `date_end == now()`: product list on-sale thì giá hiệu lực cũng
lấy giá KM (trước: scope `>=` include nhưng expr `>` exclude → lệch). Thêm chỗ mới
cần check date range → GỌI scope `dateStartToEnd()`, KHÔNG viết raw SQL riêng.

## Việc còn nợ trong `ProductRepository`

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
  rewrite. ĐÃ XOÁ toàn bộ (2026-07) — không còn file validator legacy nào.
- AccountController::detailOrder còn inject ad-hoc CheckoutPaymentService qua app(...)
  cho luồng ZaloPay redirect sau repayment — vì DI 6 dependency đã đủ dày. Acceptable.
- Route::any cho cả GET + POST cùng method controller — nếu cần REST hơn, split
  thành GET edit + POST update để type-hint FormRequest trực tiếp ở signature.

## Auth flow (refactor 2026-07-02)

AuthController (App\\Http\\Controllers\\Web\\AuthController) — luồng khách hàng:
login / register / social / forgot + reset password / verify email. Cùng pattern
Account: Controller → Service App\\Services\\Auth\\AuthService → FormRequest
App\\Http\\Requests\\Web\\Auth*Request.

- Route TÁCH GET/POST (KHÔNG còn Route::any cho auth). Mỗi form 1 GET render +
  1 POST xử lý; action POST tên `auth.doLogin` / `auth.doRegister` /
  `auth.doForgotPassword` / `auth.doChangePassword`, CÙNG URL với GET nên blade
  giữ nguyên `action="{{ route('auth.login') }}"` — không phải sửa form. Nhờ tách
  route, action POST `do*` type-hint thẳng FormRequest ở signature (validation
  chỉ chạy trên POST) → bỏ được `app(FormRequest)->validated()` kiểu Route::any cũ.
  * Ngoại lệ `doChangePassword`: giữ `app(AuthResetPasswordRequest)->validated()`
    SAU khi check token (link chết thì không validate mật khẩu). Token đọc từ
    hidden field `request()->get('token')` trong blade change_password.

- Chống brute-force: `doLogin` dùng RateLimiter key `login:<email>|<ip>`, 5 lần
  sai / 60s; quá ngưỡng flash `messages.auth.throttle` kèm `:seconds`; login OK
  thì `RateLimiter::clear`.

- i18n: mọi thông điệp auth gom vào nhóm `messages.auth.*` (login_failed,
  register_failed, social_failed/social_success dùng `:provider`,
  verify_email_sent, email_not_found, reset_link_sent, token_invalid,
  password_changed, throttle). ĐÃ XOÁ key legacy PascalCase (LoginWithProvider*,
  HasSendMail*, MemberNotFound, TokenInvalid, ChangePasswordSuccess).
  `ErrorAction` giữ vì dùng chung nhiều controller.

- `decodeToken`: `explode('+', base64_decode($token), 2)` — limit 2 để email
  chứa dấu '+' không bị tách sai (code = time().uniqid không chứa '+').

- Blade `account/_menu_left`: bỏ helper legacy `getCurrentRouteName()` (đã xoá →
  gây 500 cả khu account) → `request()->routeIs('account.*')`; đóng lại thẻ
  `</li></ul></div>` vốn thiếu trong file gốc.

## Legacy controller cluster → Web (refactor 2026-07-02)

Dọn các controller còn ở namespace/base legacy `Client\\InfunStudio` +
`BaseInfunStudioController` (đã xoá) → chuẩn `App\\Http\\Controllers\\Web` extends base
`Controller`. Nguồn migrate view: project legacy `mt219` (cạnh `infun`).

- `MaintenanceController` — `view('web::page.maintenance')` standalone (không kéo
  query của render() base khi đang bảo trì).
- `OrderController` — inject `OrderRepositoryInterface`; `search()` dùng
  `getOrderByInvoiceNo` (public, không login), render `web::order.search` (raw model:
  info + người nhận mask PII + timeline `ordersHistories` + items).
- `CsrfTokenController` — sửa namespace + `respondSuccess(Session::token())`.
- `TagController` — inject `BlogTagRepositoryInterface`, chỉ còn `getList()` →
  `web::tag.list` (action tag-detail cũ không route → bỏ).
- `BlogCategoryController` — reachable qua slug resolver (`Controller::getControllerBySlug`
  map `url.blog_category`='bc'). Inject 4 repo interface, lọc `filter[category_id]`,
  tái dùng `web::blog.list` (khuôn `BlogController::getList` + breadcrumb/SEO category).
- `ErrorController` — `client.infunstudio.page.error404` → `web::page.error404`
  (migrate: `array_get`→`data_get`, `resizeImage`→`thumbnail`). Thêm lang `seo.404.*`.

View mới: `web::page.maintenance`, `web::order.search`, `web::tag.list`,
`web::page.error404`. Helper mới: `string2Stars($s, $first, $last, $rep='x')` mask PII.

### Gotcha Blade `@context` (Laravel 12)
JSON-LD `{"@context":..., "@type":...}` trong blade PHẢI escape `@@context`/`@@type` —
Laravel 12 có directive `@context` (Context facade); Blade biên dịch nhầm `@context`
thành PHP → ParseError "unexpected end of file, expecting endif". Mọi view SEO có
JSON-LD phải dùng `@@`.

### Bug site-wide đã vá: Controller::toUrl 500 mọi trang 404
`toUrl()` gọi `fireEvent('before/after_redirect')` (trait BaseEvent) → ném lỗi ở nhánh
redirect → MỌI `$this->toUrl('error.404')` (7 controller Blog/Category/Home/Information/
Manufacturer/Product/StoreReview) 500 thay vì hiện trang 404. Đã rewrite `toUrl` thành
redirect thuần (bỏ event hook thừa, không có listener).

## Checkout — Promotions (coupon / voucher / gift / reward) 2026-07

`PromotionService::resolveAppliedPromotions($cartItems, $subtotal, $hasShipping)` dựng
`CheckoutPromotions` (items + appliedCoupons + appliedVoucherCodes + appliedGifts). Validate
theo TỪNG STAGE (không "tin session mù"):
- **Coupon** — validate SỚM trong resolveAppliedPromotions → `CouponService::applyCodes`
  (re-query Coupon::active() + validateForCart cho mã trong session). Cần thiết: coupon session
  có thể hết hạn / bị tắt / hết lượt / giỏ đổi.
- **Voucher** — resolveAppliedPromotions chỉ lấy CODES (hiển thị). Số tiền + validate thật ở
  `CheckoutTotalService::lineVouchers` → `resolveVouchers` → `VoucherService::resolveApplied`
  (phụ thuộc running total nên làm muộn — KHÔNG phải trust session).
- **Gift** — không có giá trị tiền (lineGifts chỉ đếm). Prune + record (dưới).

### Gift — tự gỡ khi không còn hợp lệ (2026-07)
`GiftService::pruneInvalid($cartItems, $subtotal)` gỡ khỏi session quà đã tắt / giỏ giảm dưới
ngưỡng / mất SP trigger / pick sai (re-validate `validateTrigger` + `validatePicks`), chỉ ghi
session khi có thay đổi. Gọi trong `resolveAppliedPromotions` (chạy cho index/cart/recalc/
saveOrder). `CheckoutController::cart()` LUÔN build promotions (kể cả giỏ rỗng) để prune cả khi
giỏ trống. Lớp 2: `recordOrderGifts` re-validate trigger+picks lúc GHI ĐƠN (an toàn cuối) —
chống cấp quà cho giỏ không còn đủ điều kiện.

### Coupon — memoize cart context
`applyCodes` (resolveAppliedPromotions) và `listForCart` (viewData) đều gọi `buildCartContext`
(→ `resolveCartCategoryIds`, query `product_category`). Đã memoize `$cartContextCache` per
instance (key `subtotal|user|shipping|productIds`) → 1 render resolve category 1 lần thay vì 2.
`listForCart` vẫn validate cả mã đã áp (để hiển thị trong list) — lượt đó không bỏ được.

## `minimum` (MOQ) → `product_variant` (2026-07)

`minimum` move từ `product` → `product_variant` (migration `2026_07_03_..._move_minimum...`,
backfill + drop cột cũ), cùng tầng price/stock/sku. `$product->minimum` giữ hoạt động qua
accessor `Product::getMinimumAttribute()` → `defaultVariant?->minimum ?? 1` (mirror
getPriceAttribute). Ghi qua `ProductVariantWriter` (`$v['minimum']`); bỏ khỏi
`ProductWriteService::FLAT_FIELDS`. Cart đọc `$variant?->minimum`; `CartService::validateMinimum`
kiểm THEO TỪNG DÒNG variant (per-SKU) thay vì gộp theo product. `ProductResource` expose
`product_variants[].minimum` (CMS round-trip; ô nhập trên form React là TODO).

## Hệ điểm thưởng (Reward) — HYBRID backend DONE 2026-07-10, còn endpoint + UI

Chuyển từ mô hình OpenCart (points-price per product) sang hybrid. Migration
`2026_07_10_000000_reward_hybrid_schema` (thêm `user_reward.expires_at` + index,
backfill `status` NULL→1, DROP `orders.reward` varchar legacy, seed 5 setting).

**TÍCH điểm (earn)** — `CartService::resolveReward(product, price, qty)`:
nguồn chính bảng `product_reward` theo user group (eager-load constrain
`getUserGroupId()`, CMS tab Reward nhập — đóng vai trò OVERRIDE per-product);
fallback earn-rate toàn cục `config_reward_earn_divisor` (X đồng = 1 điểm,
0 = tắt fallback). Row ghi ở `CreateOrderService::writeUserReward` →
`recordOrderReward` với **status=PENDING**.

**Vòng đời theo trạng thái đơn** — `OrderRewardObserver` (đăng ký trong
AppServiceProvider) bắt `Orders.updated` khi `order_status_id` đổi (mọi flow đều
qua `orderRepo->upsertOrder` = Eloquent save nên observer cover hết):
- status ∈ `order_complete_status_all` → `activateOrderReward`: PENDING→AVAILABLE,
  gán `expires_at = now + config_reward_expiry_months tháng` (0 = NULL = vĩnh viễn).
- status = `order_cancel_status_id` → `revokeOrderReward` (earn → REVOKED) +
  `refundRedeem` (hoàn điểm đã tiêu bằng row dương type=14, idempotent).

**TIÊU điểm (redeem)** — `CheckoutTotalService::lineReward` hybrid: điểm = tiền
(`config_reward_redeem_rate`: 1 điểm = X đồng), cap `config_reward_redeem_max_percent`
% giá trị đơn; clamp theo balance; KHÔNG còn phân bổ theo `item.points` — key
`points` trong cart item + `CartService::resolvePoints()` đã XÓA (không consumer;
cột `product.points`/`variant.points` thành vestigial, CMS vẫn ghi được nhưng
checkout không đọc). Row totalData
code=reward mang thêm key `points` = điểm thực tiêu sau cap →
`CreateOrderService::writeRewardRedeem` ghi row ÂM type=13 (idempotent).

**Ledger semantics** — enum `App\Enums\RewardTransactionType` (OnOrder=12,
Redeem=13 âm, RedeemRefund=14 dương) + `App\Enums\RewardStatus` (Pending=0,
Available=1, Revoked=2); repo query bằng `->value`.
`getTotalPoints` = SUM(points) WHERE status=1 AND (expires_at NULL OR > now).
Edge chấp nhận: điểm activated bị tiêu rồi đơn mới hủy → balance âm tạm.

**Settings seed** (bảng `setting`, admin đổi trong CMS): `config_reward_point_enabled`=1,
`config_reward_earn_divisor`=100, `config_reward_redeem_rate`=1,
`config_reward_redeem_max_percent`=50, `config_reward_expiry_months`=0.

**Semantics tiền tệ (chốt 2026-07-10)** — `config_reward_earn_divisor` (X = 1
điểm) và `config_reward_redeem_rate` (1 điểm = X) đơn vị **BASE CURRENCY**.
An toàn vì kiến trúc giá kiểu OpenCart: DB lưu giá base, mọi tính toán nội bộ
(resolvePrice → totals → orders.total, earn/redeem) chạy base; đa tiền tệ CHỈ
là lớp hiển thị (`CurrencyService::convertPrice/formatPrice` nhân
`currency_value` lúc render, order snapshot currency_code/value). KHÔNG BAO GIỜ
tính earn/redeem trên số tiền đã convert. Nếu sau này chuyển sang niêm yết/thu
tiền native từng currency → phải đổi scalar này thành per-currency map hoặc
earn theo % (currency-neutral). Đi kèm: `CheckoutTotalService::money()` đã bỏ
hardcode `'đ'` → delegate helper `money()` global (CurrencyService::formatPrice)
để mọi dòng totals (subtotal/coupon/ship/reward) hiển thị đúng currency đang chọn.

**Redeem endpoint (DONE 2026-07-10)** — `CheckoutRewardController`:
GET `checkout/reward` (balance + applied, withoutMiddleware cache_page),
POST `checkout/reward/apply` (validate: enabled/auth/cart/points>0/≤balance
→ SET session.reward), POST `checkout/reward/remove` (forget). Endpoint chỉ
validate cho UX — nguồn sự thật là `lineReward` (tự clamp balance + cap lúc
build totals). Lang: `messages.checkout.reward.*`.

**UI storefront (DONE 2026-07-10):**
- Checkout: `_reward_promo_row.blade.php` (Alpine `rewardBox()`, input điểm +
  apply/remove gọi 2 POST endpoint, reload theo pattern voucher modal);
  CheckoutController@index truyền `rewardEnabled/rewardBalance/rewardApplied`.
- Product page: `RewardEarnService::perUnit()` (extract từ CartService::
  resolveReward — CartService giờ delegate qua service này, inject constructor)
  → ProductController@index truyền `rewardEarn` → block "Mua nhận X điểm"
  dưới giá. firstWhere user_group nên chạy cả khi productRewards load đủ group.
- Account: route GET `account/rewards` (auth group) → AccountController@rewards
  → view `account/rewards.blade.php` (balance badge + bảng ledger phân trang
  `getHistoryForUser`, label từ enum RewardStatus/RewardTransactionType, hàng
  hết hạn hiện badge "Hết hạn"); menu item trong `_menu_left`. `UserReward`
  cast `expires_at => datetime`.

**CÒN THIẾU (optional):**
- **Job quét expires_at**: balance query đã tự loại điểm hết hạn, job chỉ cần
  nếu muốn ghi row EXPIRE tường minh cho user xem lịch sử.

## Hệ Affiliate — Phase 1 (DB/models/repos) DONE 2026-07-10

Kế hoạch đầy đủ + business đã chốt: xem `AFFILIATE-PLAN.md`. Tóm tắt:
- Migration `2026_07_10_000001_create_affiliate_tables.php`: 7 bảng
  (affiliate, affiliate_link — short link `/l/{slug}`, affiliate_click —
  click_token kiểu uls_trackid Shopee, affiliate_conversion — ledger hoa hồng
  1 đơn/1 affiliate, affiliate_payout, affiliate_coupon — coupon riêng KOL,
  affiliate_commission_rule — % theo category). orders DROP
  tracking/commission/marketing_id (vestigial OpenCart), affiliate_id thành
  FK SET NULL. Seed 6 `config_affiliate_*`.
- Enums AffiliateStatus / AffiliateConversionStatus / AffiliatePayoutStatus;
  7 models; repos Affiliate / AffiliateLink / AffiliateConversion (idempotent,
  sẵn approve/reject cho observer Phase 3 — pattern giống reward).
- CHÚ Ý kiểu FK: user.id BIGINT UNSIGNED, coupon/category/orders.id INT
  SIGNED → PK affiliate INT SIGNED.

**Phase 2 (tracking) DONE 2026-07-10**: route `GET l/{slug}` →
`AffiliateRedirectController` (log click server-side tại redirect, token 12
ký tự, cookie `aff_ref`, 302 kèm auto-UTM, safeDestination chống
open-redirect); middleware `TrackAffiliateRef` append web group
(bootstrap/app.php — aff_click validate-only, ?ref= log click, last-click
ghi đè cookie, exception nuốt + logError); `AffiliateAttributionService`
(Services/Affiliate — coupon KOL > cookie token, chặn self-referral, trả
`AffiliateAttribution`); core config `affiliate.*` (cookie/params/throttle).
**Phase 3 (conversion) DONE 2026-07-10**: `AffiliateConversionService`
(base = totalData sub_total + dòng âm coupon/reward/voucher — sau discount
TRƯỚC ship; rate per-item: KOL flat > category rule max > global; ghi
conversion PENDING + set orders.affiliate_id qua query-builder không fire
observer); gọi từ `CreateOrderService::writeAffiliateConversion` (try/catch —
không phá đặt hàng); `OrderAffiliateObserver` (approve khi complete, reject
khi cancel, mirror OrderRewardObserver).
- Phase 4-6 (portal affiliate trong account, admin/payout, anti-fraud)
  CHƯA làm — theo AFFILIATE-PLAN.md mục 3.

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

> ⚠️ **CẬP NHẬT 2026-07-06 — phần dưới là LỊCH SỬ.** `product_option` GIỜ CÓ
> surrogate PK `id` (BIGINT UNSIGNED AUTO_INCREMENT); khóa tự nhiên
> `(product_id, option_id)` thành **UNIQUE**. `product_option_value` thêm FK
> `product_option_id → product_option(id)` CASCADE (giữ `product_id/option_id`
> denormalized vì nhiều chỗ đọc trực tiếp). Model `ProductOption` bỏ composite
> PK (`$primaryKeyAutoIncrement = 'id'`), relation `productOptionValues` +
> `ProductOptionValue::productOption` dùng `product_option_id` — Compoships
> KHÔNG còn cần cho quan hệ này. Migration
> `2026_07_06_000000_add_surrogate_id_to_product_option.php`.
> **Đã dọn nốt (2026-07-06):** drop `product_id/option_id` thừa khỏi
> `product_option_value` (chỉ còn `product_option_id` + `option_value_id`),
> UNIQUE giờ `(product_option_id, option_value_id)`. Caller lấy product_id/
> option_id qua parent `ProductOption`. Migration
> `2026_07_06_000001_drop_denormalized_cols_from_product_option_value.php`.
>
> **Soft delete + giá custom field (2026-07-06):** cả `product_option` và
> `product_option_value` giờ có `deleted_at` (SoftDeletes, đồng bộ
> `option/option_value`). Bất biến: **1 dòng vật lý / natural key** — re-declare
> thì RESTORE (ProductVariantWriter `withTrashed()` + set `deleted_at = null`),
> KHÔNG tạo dòng mới → UNIQUE giữ nguyên, không cần partial index. Cascade
> soft-delete lo bởi `HasCascadeRelations` + `$destroyRelations`. Thêm cột
> `price DECIMAL(15,4)` **có DẤU** (âm = giảm; KHÔNG dùng `price_prefix`
> OpenCart) cho phụ phí custom field: `product_option.price` (field cố định) +
> `product_option_value.price` (từng lựa chọn picker); default 0 nên chưa đổi
> giá đơn. Đã expose ra `ProductOptionService`. Migration
> `2026_07_06_000002_add_soft_delete_and_price_to_product_option_cluster.php`.
>
> **Wiring giá vào cart (2026-07-06):** `CartService::customOptionsSurcharge()`
> tính phụ phí per-unit của 1 line (picker → `product_option_value.price` của
> value đã chọn; field nhập tự do → `product_option.price` khi có value), cộng
> vào `resolvePrice()` NGAY trong `getItems()` — nguồn giá DUY NHẤT. Nhờ vậy lan
> tự động tới `getSubtotal()` → `CheckoutTotalService` (sub_total) → snapshot đơn
> (`CreateOrderService` dùng `$item['price']`/`['total']`). `splitOptionPayload`
> + `makeKeySession` giờ mang `option_value_id` để mỗi lựa chọn picker (giá khác
> nhau) thành line riêng. price có DẤU (âm = giảm); SoftDeletes tự loại option đã
> xoá khỏi phụ phí.

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
- Click lại swatch đã `.active` → de-select: uncheck, remove `.active`,
  `resetMainSlider()` về slide 0, rồi `recompute(optionId)` áp lại giá/stock.
- Native radio không hỗ trợ uncheck via click → handler dùng
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

**Shopee-style availability — BIDIRECTIONAL (cập nhật 2026-07):**
- `refreshAvailability()`: value V của option O gắn `.out-of-stock` khi
  `hasSelection` && KHÔNG tồn tại in-stock variant khớp
  (selection các option KHÁC) ∪ {O:V}. ĐÃ BỎ short-circuit "option đã chọn
  thì mọi value enable" → cả trục Màu lẫn Size cùng grey nhất quán.
- Swatch out-of-stock KHÔNG hard-disable — vẫn click được. Click 1 combo
  không tồn tại → `recompute(optionId)` auto-resolve: bỏ chọn trục xung đột
  (giữ value vừa click) nên không bao giờ kẹt ở combo không mua được.
- Value đang chọn không bao giờ tự đánh `.out-of-stock`. Init (chưa chọn) → ALL enable.
- CSS `.out-of-stock` (custom.css): opacity 0.45 + diagonal stripe + grayscale
  + line-through; ĐÃ bỏ `pointer-events:none` ở label để swatch click được.

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

## Naming — độ dài & rõ nghĩa (BẮT BUỘC cho code commit)

Áp dụng cho MỌI code ghi vào repo (PHP / JS / blade). KHÔNG áp cho script
dùng-một-lần (lệnh bash/python migrate, đoạn chạy trong console browser) —
chỗ đó cho phép gọn để chạy nhanh.

- Biến / hàm / method PHẢI đặt tên rõ nghĩa, đọc là hiểu vai trò. KHÔNG
  viết tắt 1 ký tự kiểu `$d`, `$s`, `$b`, `$o`, `function g()`, `function P()`.
  Dùng `$data`, `$payload`, `$response`, `respondSuccess()`, `handleCartError()`...
- KHÔNG tiền tố `_` cho method (đã bỏ `_getFile` → `uploadedFile`, `_getStorage` → ...).
- Biến trong closure/loop ngắn được phép gọn NHƯNG vẫn có nghĩa
  (`$item`, `$row`, `$variant`), không dùng 1 ký tự.
- Tên tự-document nguồn dữ liệu (xem mục "Naming variable / view-data key").
- Lý do: code trong repo bị đọc lại / review / bảo trì lâu dài → tên rõ
  nghĩa quan trọng hơn tiết kiệm vài ký tự khi gõ.

## Response API — envelope thống nhất `respond*` (2026-07)

Helper ở `app/Common/Common.php`. MỌI endpoint JSON dùng bộ này, KHÔNG dùng
lại `successData/errValidator/errNoValidator/successNoData` (ĐÃ XOÁ).

- Success 2xx: `respondSuccess($data, $msg, $status=200, $meta=[])`,
  `respondCreated($data,$msg)` (201), `respondAccepted($data,$msg)` (202),
  `respondMessage($msg,$status=200)` → body `{ success:true, message, data(, meta) }`.
- Error 4xx/5xx: `respondError($msg,$status,$errors=[])`,
  `respondNotFound($msg)` (404), `respondUnprocessable($msg,$errors=[])` (422)
  → body `{ success:false, message(, errors) }`.
- HTTP status là tín hiệu REST chính; `success` mirror cho JS tiện check.
- Validation FormRequest: dùng trait `App\Http\Requests\Concerns\RestfulValidation`
  → 422 `{ success:false, message, errors:{field:[...]} }`. Message để trong i18n.
- JS đọc: success qua `success`/`.done` (2xx), lỗi qua `error`/`.fail`
  (đọc `json.message` + `json.errors`).

## Convention: hàm KHÔNG quá nhiều tham số → Parameter Object

**Rule — quá ~4 tham số (nhất là khi có "data clump" đi cùng nhau qua nhiều
hàm) thì gom thành 1 value object, đừng truyền rời.**

Dấu hiệu cần gom:

- Một cụm tham số luôn đi cùng nhau, tính 1 lần rồi *thả xuyên* qua nhiều method
  (vd cụm cart: `subtotal` + `productIds` + `categoryIds` + `userId` +
  `hasShipping`).
- Nhiều tham số cùng kiểu cạnh nhau (`int`, `?int`, `?int`...) → dễ tráo thứ tự.
- Thêm 1 thuộc tính phải sửa chữ ký ở mọi call site.

```php
// BAD — 7 tham số, cụm cart thả rời, dễ nhầm thứ tự
public function validateForCart(
    Coupon $coupon,
    int $cartSubtotal,
    array $cartProductIds,
    array $cartCategoryIds,
    ?int $userId,
    bool $contextHasShipping = true,
    ?int $usedByUser = null,
): ?string

// GOOD — gom cụm cart thành value object → còn 3 tham số
public function validateForCart(
    Coupon $coupon,
    CartCouponContext $cart,   // subtotal/productIds/categoryIds/userId/hasShipping
    ?int $usedByUser = null,
): ?string
```

Value object = `final class` + constructor promoted `readonly`, và **một class
một file** (PSR-4 — KHÔNG nhét 2 class chung 1 file kể cả khi ngại tạo file).
Tham chiếu thực: `app/Services/Cart/CartCouponContext.php`, dùng ở
`CouponService::validateForCart` / `listForCart` / `applyCodes` (build context
1 lần qua `buildCartContext()`, hết lặp tính `productIds`/`categoryIds`).

KHÔNG over-engineer:

- ~2-4 tham số khác nhau, không phải clump → để nguyên (vd
  `recordApplied(couponId, userId, amount)`).
- PHP 8 named arguments đã giảm rủi ro thứ tự — nếu chỉ vướng readability mà
  không có clump/không mở rộng thì named args là đủ, khỏi tạo VO.
- Giảm tham số bằng *suy ra nội bộ* khi giá trị luôn là "hiện tại" (vd
  `userId`/`userGroupId` resolve từ `getCurrentUserId()`/`getUserGroupId()` ngay
  trong hàm) — nhưng KHÔNG inject `CartService` để đọc giỏ ngầm (coupling +
  khó test).

## Convention: KHÔNG hard-code text hiển thị → i18n

**Rule — mọi chuỗi hiển thị cho người dùng (label, thông báo, lỗi) PHẢI nằm
trong file lang và gọi qua `trans()`; KHÔNG viết literal trong code.**

Áp cho cả service/controller, không riêng blade. Lý do: gom 1 chỗ, đa ngôn ngữ,
tránh trùng lặp, sửa wording không phải lục code.

```php
// BAD — literal trong service
$errors[] = "Mã '{$code}' không tồn tại";
return 'Cần đăng nhập';

// GOOD — key i18n; chuỗi có biến thì sprintf
$errors[] = sprintf(trans('messages.checkout.coupon.not_found'), $code);
return trans('messages.checkout.coupon.need_login');
```

Quy ước key:

- Gom theo **nhóm lồng (2 chiều)** theo domain, không để phẳng prefix-soup:
  `messages.checkout.coupon.*`, `messages.checkout.gift`... (xem
  `resources/lang/vi/messages.php`). Cụm lớn có thể tách file riêng
  (`lang/vi/checkout.php` → `trans('checkout.coupon.x')`).
- Chuỗi có biến: dùng `%s` + `sprintf(trans(...), $x)` (đồng bộ convention sẵn
  có), hoặc placeholder `:name` của Laravel.
- Locale dự án: `vi` (`APP_LOCALE` / `APP_FALLBACK_LOCALE = vi`).

Ngoại lệ — KHÔNG cần i18n:

- "code"/key nội bộ không hiển thị: `'sub_total'`, `'coupon_freeship'`,
  `'voucher:'.$code`...
- Ký hiệu định dạng số thuần (vd `'đ'` trong `money()`) — tuỳ, có thể đọc
  `config_currency`.
- Log / exception message kỹ thuật cho dev (không show cho khách).

Tham chiếu thực: `CheckoutTotalService` + `CouponService` đã chuyển toàn bộ text
hiển thị vào `messages.checkout.*` (2026-06-19).

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
- (2026-07) Cart serialization ĐÃ bỏ key thừa `product_option_id`, chỉ còn
  `option_id`; `CreateOrderService` đọc `option_id` (vẫn ghi cột DB
  `product_option_id`). Còn lại DUY NHẤT: rename cột DB
  `orders_product_option.product_option_id → option_id` (bullet đầu mục này).
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

- `App\Services\Cart\CartService::splitOptionPayload` (line 275) — ưu tiên
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
- (2026-07) ĐÃ unify: `effectivePriceExpression` cả 2 nhánh dùng `date_end >= ?`
  khớp scope `dateStartToEnd` → hết drift toán tử ngày.

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
- Rule availability = **bidirectional** (cập nhật 2026-07): value grey khi
  không có in-stock variant khớp (selection option KHÁC) ∪ {O:V}; swatch
  out-of-stock vẫn click được, `recompute()` auto-resolve trục xung đột.
  (Chi tiết ở mục "JS interaction pattern — variant".)
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

### Session key cart/checkout — centralize trong core config (2026-06-18)

9 session key cart/checkout giờ nằm trong `config/core/config.php →
core.config.session`, đọc qua `getCoreConfig('session.*')` — KHÔNG hardcode
literal nữa. Trước đây 1 key lặp 8–11 lần qua 3 file → typo 1 chỗ = drift; bất
biến "`clear()` phải forget đúng key mà writer `put()`" chỉ được con người gõ
khớp. Mapping:

| `getCoreConfig('session.x')` | value (chuỗi session thật) |
|---|---|
| `session.cart` | `cart` (prefix → `cart.{key}`) |
| `session.cart_header` | `total_cart_header` |
| `session.cart_shipping` | `cart_shipping` |
| `session.reward` | `reward` |
| `session.last_order` | `lastOrderSuccess` |
| `session.applied_coupons` | `checkout.applied_coupons` |
| `session.applied_gifts` | `checkout.applied_gifts` |
| `session.applied_vouchers` | `checkout.applied_vouchers` |

- **GIỮ NGUYÊN value** — nhất là dot-notation `checkout.*` (= mảng lồng dưới cha
  `checkout`, xem mục trên). Đổi value = orphan session khách đang checkout +
  tái phát bug prefix. Thêm key mới: thêm 1 dòng vào block, code gọi `getCoreConfig`.
- `CartService::clear()` forget tất cả qua `getCoreConfig('session.*')` → một
  nguồn sự thật, hết nguy cơ clear() quên key. `total_wishlist` đã ở block này từ
  trước (WishlistService). Sau khi sửa config phải `php artisan config:clear`.

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

`CheckoutController::addToCart` gọi `tryAdd`, khi `ok=false` trả
`respondUnprocessable(...)` (envelope RESTful — xem mục "Response API `respond*`")
với message giàu ngữ cảnh: "bạn đã có K trong giỏ, yêu cầu thêm Q (tổng T)
nhưng kho chỉ còn N". Nếu tổ hợp biến thể không tồn tại → `variant_error`
→ message `messages.ErrorVariantNotFound`.

### `CreateOrderService::subtractStock` — strict + audit

1. `variant_id` null → `logError(...)` + return (không silent bump
   `product.quantity` nữa).
2. Lookup `product_stock` với `lockForUpdate()` để chống TOCTOU.
3. Policy gating:
   - `untracked` → no-op.
   - `deny` → decrement (CartService đã gate sẵn, lock chống concurrency).
   - `backorder` → decrement, cho `on_hand` âm. `stock_movement.type =
     sale_backorder` để admin filter ra backlog.
4. `version` tăng 1 mỗi UPDATE → optimistic lock infrastructure sẵn s�
## CMS REST API (`rcms`) — migration Vue2 → React (2026-06)

CMS admin tách hẳn thành SPA React standalone (`infun_cms`, repo riêng cạnh `infun`),
gọi API qua prefix **`/rcms`**. Storefront web giữ nguyên (Blade + envelope cũ).
Xem thêm `infun_cms/CLAUDE.md` cho phía frontend.

### Route + area
- `routes/rcms.php`, nạp ở `RouteServiceProvider::mapCmsApiRoutes()` — prefix `rcms`,
  **area `rcms`**, middleware `auth:sanctum` (trừ `login`, `system/init` public).
- Macro `Route::cmsApiResource('product', ProductController::class)` =
  `apiResource` + route phụ `restore` + `bulk`, tất cả bọc middleware group
  `cms.permission`. (Định nghĩa ở `AppServiceProvider::registerRouteMacros()`.)
- App khách (Android) tách riêng: prefix `api/v1`, `mapMobileRoutes()`, ability `mobile`.

### Phân quyền — spatie/laravel-permission v6
- Middleware `CmsPermission` map HTTP method → action:
  `index→list, show→detail, store→create, update→edit, destroy/restore/bulk→del`,
  rồi `Gate::authorize("$action-$slug")`. `$slug` lấy từ `controller->permissionName()`
  (khai ở `BaseCmsController::$permission`). Bảng cấu hình `sp_*`.

### Output — Spatie Data DTO ở `app/Data/Cms/`
- KHÔNG dùng `JsonResource` cho CMS nữa (các `*Resource` cũ bỏ được). Dùng DTO
  `extends Spatie\LaravelData\Data`, **property snake_case** (khác `app/Data/Output`
  storefront dùng camelCase) để khớp contract REST FE. `fromModel()` map tay.
- Response REST: `{data}` cho item, `{data,meta,links}` cho list
  (`XxxData::collect($paginator)`). Web storefront **GIỮ envelope cũ**
  `{success,validator,code,message,data,totalRow}` — cố ý, rủi ro đụng checkout/tiền.

### Repository CMS — quy ước split
- **Mặc định**: read CMS trộn chung repo entity (vd Category — entity nhỏ).
- **Tách `XxxCmsRepository` riêng** chỉ cho entity nặng / 2 audience phân kỳ
  (vd Product: no-cache vs cache, đa-ngôn-ngữ vs forLocale, withTrashed vs active).
- ⚠️ **Repo CMS phải `extends QueryableRepository`, KHÔNG `extends BaseRepository`** —
  `BaseRepository implements BaseRepositoryInterface` nhưng KHÔNG định nghĩa
  `list/listAll/getSortMenu/getPerPageMenu` (4 method này ở `QueryableRepository` +
  trait `HasListFilterToolbar`). Extends thẳng `BaseRepository` → class concrete
  thiếu 4 method → fatal lúc container resolve.
- Cache invalidation vẫn tập trung 1 chỗ: `$cacheMap` map model → repo **storefront**
  + observer; repo CMS KHÔNG cache. Interface CMS (`ProductCmsRepositoryInterface`)
  KHÔNG extends `BaseRepositoryInterface` (chỉ khai các method read CMS cần).

### Product write — Service + Writer (tránh god class)
- `ProductWriteService::save(?Product, array)` trong transaction: sync
  descriptions / categories / filters / related / ingredients / attributes / images /
  discounts / rewards (Eloquent) + `ProductVariantWriter::sync()` (cluster variant mới:
  `product_variant` + `_attribute` + `product_stock`, role-based `product_option`).
- Dùng **Eloquent** (không `DB::table` bulk) để observer bắn → flush cache.
  `product.price` đã DROP → giá đọc/ghi qua `defaultVariant`.
y::rememberEntity($entity, $prefix, $resolver, $ttl,
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

## Đổi tên CheckoutContext → CouponCheckoutContext + dọn tên hàm (2026-06-19)

- `CheckoutContext` → **`CouponCheckoutContext`** (file đổi theo). Lý do: miền
  checkout có coupon/voucher/gift nhưng object này CHỈ mang state coupon
  (`appliedCoupons` / `hasFreeshipCoupon`); voucher & gift đọc thẳng từ session
  trong `CheckoutTotalService` / `CreateOrderService`. Tên cũ quá rộng → hiểu
  nhầm nó giữ toàn bộ state checkout.
- **Bỏ** 2 field chết: `totalCouponDiscount` (có ghi, 0 caller đọc) +
  `userGroupId` (0 ghi 0 đọc). `setAppliedCoupons()` bỏ tham số `$totalDiscount`
  → còn `(array $applied, bool $hasFreeship)`. Caller: `CheckoutController` +
  `CheckoutCouponController`.
- `CheckoutController` đổi tên hàm cho đúng nhiệm vụ: `extractItems()` →
  **`validateCart()`** (thực chất validate stock + tối thiểu, trả `[lỗi, items]`);
  `shipping()` → **`recalcTotals()`** (endpoint tính lại tổng theo carrier; route
  `checkout.shipping` + URL `shipping` GIỮ NGUYÊN, chỉ đổi `@shipping` →
  `@recalcTotals` trong `routes/web.php`); `sendNotifications()` →
  **`sendOrderEmails()`**; `buildMailData()` → **`buildOrderMailData()`**;
  `buildContext()` → **`buildCouponContext()`** (sau đó gộp tiếp, xem mục dưới).

## Gộp KM: PromotionService facade + CheckoutPromotions (2026-06-19)

- **Bối cảnh:** 3 cơ chế KM (coupon / voucher / gift) trước đây rải rác — coupon
  trong context, voucher/gift đọc thẳng session — lại có 3 service + 3
  sub-controller đối xứng. Gộp ở tầng "đường ống", KHÔNG gộp tầng dữ liệu (mỗi
  loại giữ bảng + lifecycle riêng: `coupon_history` / `voucher_history` /
  `order_gift`).
- **`PromotionService`** (mới, `App/Services/Checkout`) = 1 cổng gói 3 service con
  (delegation thuần, KHÔNG đổi logic): `buildContext()`, `viewData()`,
  `resolveVouchers()`, `recordForOrder()`, `revertForOrder()`, `recordCoupons()`.
- **`CouponCheckoutContext` → `CheckoutPromotions`** (broad name nay hợp lý vì
  ôm cả 3): thêm `appliedVoucherCodes` + `appliedGifts` (+ setters) bên cạnh
  `items` / `appliedCoupons` / `hasFreeshipCoupon` / `orderId`.
- **Rewire:** `CheckoutController` inject DUY NHẤT `PromotionService` (bỏ 3
  service); `buildCouponContext()` → **`buildPromotions()`** (delegate facade);
  index/cart dựng view qua `viewData()`. `CheckoutTotalService` inject facade,
  voucher qua `resolveVouchers()` (gift line vẫn đọc session — hành vi giữ
  nguyên). `CreateOrderService` thay 3 hàm `writeCouponHistory/writeNewVouchers/
  writeGifts` bằng `recordForOrder()`. `AccountService::cancelOrder` thay 3
  revert bằng `revertForOrder()`.
- 3 sub-controller (`CheckoutCoupon/Voucher/GiftController`) GIỮ service con của
  mình (mỗi cái chỉ 1 loại KM) — facade dành cho pipeline gộp. Có thể gộp tiếp
  sau nếu muốn.

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

## Docker dev workflow — toàn bộ stack chạy local (2026-06-24)

Refactor từ XAMPP (Apache/MySQL trên Windows) sang full-Docker stack. Mọi
service chạy container, code mount qua bind volume cho hot reload.

### Layout file Docker

```
infun/
  docker/
    php/Dockerfile          # PHP 8.2-FPM Alpine + ext (pdo_mysql, redis,
                            # gd, intl, bcmath, zip) + Composer
    php/entrypoint.sh       # auto composer install + fix quyền storage/
    nginx/default.conf      # nginx vhost forward .php → FPM
    nginx/default.lb.conf   # phiên bản LB: upstream pool + DNS resolver
                            # (cho load balance test, scale FPM)
    k6/load-test.js         # k6 load test script
  docker-compose.yml        # stack chính (php, nginx, vite, cms, mysql,
                            # redis, redis-insight, meilisearch)
  docker-compose.lb.yml     # override: bỏ container_name FPM để scale +
                            # swap nginx LB + service k6
  DOCKER.md                 # hướng dẫn đầy đủ end-user (chạy, seed, test)
```

### Services chạy

| Service | Port host | Vai trò |
|---|---|---|
| `infun-php` | — (qua FPM socket) | PHP-FPM 8.2 |
| `infun-web` | 8000 | nginx serve public/ |
| `infun-vite` | 5174 | Vite dev cho Laravel @vite |
| `infun-cms` | 5173 | React CMS standalone (Vite) |
| `mysql` | 3306 | MariaDB 10.11 |
| `redis` | 6379 | Redis 7-alpine |
| `redis-insight` | 5540 | GUI debug Redis |
| `meilisearch` | 7700 | Search engine cho Scout |

PHP container có `memory_limit=1024M` + `max_execution_time=0` để chịu
được seed lớn (xem OOM note bên dưới).

### Console command seed/test perf (tận dụng tối đa)

Project có 6 command chuyên dụng cho seed data test perf — KHÔNG viết
lại, KHÔNG tạo seeder mới trùng:

| Command | Vai trò |
|---|---|
| `products:seed N` | Bulk insert N product + taxonomy (category, manufacturer, filter). KHÔNG variant. Default 50k. |
| `variants:seed --percent=X` | Gắn variant Color×Size cho X% product có sẵn. |
| `simple-variants:seed` | Fill 1 default variant + product_stock cho product chưa có variant (theo Shopify pattern unified stock). |
| `specials:seed --percent=Y` | Gắn campaign giảm giá lên Y% variant. |
| `products:seed-all` | **Wrapper 1 lệnh** orchestrate 4 cái trên. Default 500k product, 40% variant + 30% special. |
| `products:purge` | Truncate sạch product cluster + reset AUTO_INCREMENT. `--keep-taxonomy` / `--keep-options` để giữ category/option. |
| `products:schema-check` | Diagnostic in cột thật của bảng product, so với expected list, cảnh báo cột đã drop. **Chạy trước khi seed nếu nghi schema drift.** |

Review cluster:

| Command | Vai trò |
|---|---|
| `reviews:seed N` | Seed N review + cluster (rating, media, tag, helpful). `--no-aggregate` skip UPDATE cuối khi chạy split-run. |
| `reviews:seed-bulk N` | **Wrapper chia N thành nhiều process**, mỗi process exit free memory → vượt qua leak nội bộ PHP. Default 200k/process. **Dùng cho 1M+ review.** |
| `reviews:rebuild-aggregate` | Rebuild `product.review_count` + `rating_avg` etc. bằng 1 SQL UPDATE GROUP BY. |

### Cột đã DROP khỏi `product` table — KHÔNG ghi/đọc nữa

Mọi seeder mới + DTO + service phải tránh các cột này:

| Cột | Drop bởi | Thay thế |
|---|---|---|
| `price` | `2026_06_18_000001` | `product_variant.price` (accessor `$product->price` → `defaultVariant?->price`) |
| `quantity` | Legacy drop (sau `unify_simple_product_stock`) | `product_stock.on_hand` |
| `subtract` | Legacy drop | `product_stock.inventory_policy` (enum 0=deny, 1=backorder, 2=untracked) |
| `rating` | Legacy drop | `product.rating_avg` (DECIMAL 3,2) — observer cập nhật |
| `total_rating` | Legacy drop | `product.rating_sum` / `product.review_count` |
| `video` | Legacy drop | (nếu cần multimedia → `product_image` với `type='video'` hoặc tách bảng) |

`product_special` table đã DROP (merged vào `product_variant_special`).

`ProductWriteService::FLAT_FIELDS` là source of truth — KHÔNG include
các cột đã drop. `Product::$auditExclude` đã thay rating/total_rating
bằng rating_avg/rating_sum + min/max_variant_price.

### Architecture giá (sau drop)

```
product                              ← KHÔNG có price. Chỉ aggregate:
  min_variant_price                    min/max_variant_price + max_variant_discount_percent
  max_variant_price                    (denormalized cho list/filter, observer cập nhật)
  max_variant_discount_percent
    │
    │ 1..N
    ▼
product_variant                      ← GIÁ THẬT (kể cả simple cũng có default variant)
  price                                price          = giá đang bán
  regular_price                        regular_price  = basis tính sale %
    │
    │ 0..N
    ▼
product_variant_special              ← Override time-window + user_group
  price                                priority cao nhất thắng
  date_start/date_end                  KHÔNG ghi đè variant.price, chỉ runtime
  user_group_id, priority
```

Filter/sort theo giá → `Product::scopeEffectivePriceBetween` /
`scopeOrderByEffectivePrice` (CASE WHEN has_variants → aggregate variant
prices, ELSE → default variant + special). 1 query, không N+1.

### Load balance + k6 (test perf)

```bash
# Scale 3 PHP-FPM backend
docker compose -f docker-compose.yml -f docker-compose.lb.yml up -d --build --scale infun-php=3

# Bắn tải
docker compose run --rm k6 run /scripts/load-test.js
docker compose run --rm -e VUS=100 -e DURATION=60s k6 run /scripts/load-test.js
```

nginx LB conf dùng `resolver 127.0.0.11` (DNS embedded Docker) +
`upstream least_conn` → tự discover replicas khi scale, không reload.

### Scout + Meilisearch

`Product` có trait `Searchable` + `toSearchableArray()` index aggregate
giá (min/max_variant_price) + `category_id`/`filter_value_id` (mảng int
từ pivot). KHÔNG đẩy variant lên Meilisearch (500k×3 = 1.5M doc, không
cần). `makeAllSearchableUsing()` eager-load `description` +
`productCategories` + `productFilters` để tránh N+1 khi import.

**Index PER-LOCALE** (2026-07-13): `searchableAs()` = `products_{locale}`
(vd `products_vi`). Import bằng `php artisan products:scout-import`
(wrapper loop locale), KHÔNG `scout:import` trần — nó chỉ đẩy locale
đang active. Xem mục "Meilisearch / Scout — trang list product".

### Debugbar + Clockwork

- `barryvdh/laravel-debugbar` — widget HTML cho web routes
- `itsgoingd/clockwork` — header-based, cho API/SPA (React CMS gọi
  `/rcms/...` JSON, Debugbar không hiện)

Auto-discovery enable khi `APP_DEBUG=true` + `APP_ENV=local`. Prod
phải set `DEBUGBAR_ENABLED=false`, `CLOCKWORK_ENABLE=false`.

### OOM khi seed lớn — split-process pattern

PHP có memory leak ngầm (Carbon static + framework boot + PDO buffer)
không API nào free được trong cùng process. Ở 1M+ review, kể cả
`memory_limit=4G` cũng die ở ~50% tiến độ.

**Cách duy nhất chắc ăn:** chia thành nhiều process độc lập, mỗi process
exit → OS free memory tận gốc.

→ Đó là lý do tồn tại `reviews:seed-bulk` (wrapper) + `--no-aggregate`
flag + `reviews:rebuild-aggregate` (rebuild 1 lần cuối). KHÔNG seed
2M+ review bằng 1 process duy nhất.

### Fix container conflict khi rebuild

```
Error: Conflict. The container name "/infun-mysql" is already in use
```

→ compose `up` mặc định touch dependencies (mysql, redis) qua
`depends_on`, gặp container cũ cùng tên = conflict.

**Fix 1 dòng — chỉ rebuild + recreate `infun-php`, không đụng MySQL/Redis:**

```bash
docker compose up -d --build --no-deps --force-recreate infun-php
```

`--no-deps` = skip dependencies, `--force-recreate` = recreate container
mới với image vừa build. Mysql/Redis giữ nguyên đang chạy, data còn.

## Ghi chú công cụ khi sửa repo này

- **Một số file có byte binary** (vd `CheckoutContext.php`, nhiều `*.blade.php`,
  vài interface) → `grep` báo "binary file matches" hoặc sót dòng, đọc SAI.
  **Tin `Read` tool, KHÔNG tin `grep`** khi verify các file này.
- Sandbox dev **không có php-cli** → không chạy được `php -l` / `artisan
  migrate` / `tinker`. Verify tĩnh bằng Read + grep; chạy thật trên XAMPP. Nhớ
  `php artisan view:clear` khi đổi blade, `cache:clear` khi đổi eager-load (cache
  `getProductDetail` giữ entity với relation cũ).
- **Mount của bash lệch pha (stale) với file thật** — sau khi sửa file bằng
  Read/Write/Edit tool, `bash` (cat/grep/sed/python) có thể đọc **bản cũ** một
  lúc; ngược lại bash ghi xong thì file-tool thấy ngay. Hệ quả nguy hiểm: chạy
  `sed -i` trên file vừa sửa bằng tool → sed đọc nhằm bản stale rồi ghi đè =
  **clobber/cắt cụt file** (đã xảy ra với `VoucherService.php` +
  `CheckoutCouponController.php` khi gom session key). Quy tắc:
    * Sửa hàng loạt KHÔNG dùng `sed` cho file đã/đang đụng bằng Edit tool — dùng
      **Edit tool** (replace_all), nó báo lỗi khi không khớp, không clobber thầm.
    * Verify "file có cụt không" PHẢI bằng **Read tool** (bản XAMPP đọc), KHÔNG
      tin brace-count của bash/python (hay stale).
    * Lỡ clobber file tracked → khôi phục bằng `git show HEAD:path > path`
      (sandbox chặn `rm`/`git checkout` vì không unlink được), rồi áp lại thay
      đổi bằng Edit tool.

## Meilisearch / Scout — trang list product (2026-06-30)

Trang list sản phẩm dùng **dual engine**: Meilisearch cho keyword/manufacturer/
price/sort (attribute đã index), DB cho category/filter_value/in_stock (relation
chưa index). 500k document đã import sẵn vào index `products`.

### Entry point — `filter[keyword]`

`ProductRepository::list()` route theo `filter.keyword`:

1. Rỗng → `parent::list()` (pipeline Spatie/Eloquent gốc — category/manufacturer
   landing page không bị ảnh hưởng).
2. ≠ rỗng + `config('scout.driver') === 'meilisearch'` → `searchViaMeilisearch()`
   dispatch qua Scout.
3. Scout throw `\Throwable` (server down / index lỗi) → `logError(...)` + flash
   session `search_unavailable` → fallback `parent::list()`. **KHÔNG silent drop**
   keyword: callback `AllowedFilter::callback('keyword', ...)` chạy LIKE %name/sku/
   model% (chậm 1-3s trên 500k vì không có FTS index B-tree, nhưng giữ semantic).
4. `SCOUT_DRIVER != meilisearch` (vd dev tắt) → fallback luôn, cùng LIKE callback.

Khi keyword có giá trị + đi qua DB pipeline, `baseQuery()` tự `leftJoin
product_description` để LIKE trên `product_description.name` (cùng cơ chế với
sort=name). Meilisearch path bypass `baseQuery()` nên fast path không tốn join.

### `searchViaMeilisearch` — chia filter theo nơi có data (cập nhật 2026-07-13)

Filter ĐẨY MEILISEARCH (đã khai báo `filterableAttributes` trong `config/scout.php`):

- `manufacturer_id` → `whereIn`
- `category_id`, `filter_value_id` → `whereIn` trên SCOUT builder (doc chứa mảng
  int, Meili filter `IN` match khi bất kỳ phần tử nào khớp) — chuyển từ DB
  closure lên Meili 2026-07-13 để pagination/total/recall đúng.
- `price_min/price_max` → `where max_variant_price >= min`, `where min_variant_price <= max`
  (overlap test cho range — KHÔNG chỉ check min hoặc max một chiều)
- `sort` → `sortMap[token]['meili']`

Filter GIỮ DB (chạy trong `$builder->query()` callback sau khi Meilisearch trả IDs):

- `in_stock` — `whereExists` join `product_stock`, value động không nên index
  (TODO 2: denormalize `has_stock`)
- `cardRelations` eager-load + `dateAvailable` scope + `$modifyBase` closure
- eager-load `productFilters` đã chọn (CHỈ để hiển thị — lọc đã làm ở Meili)

**Trade-off còn lại**: `paginator->total()` = Meilisearch totalHits. Chỉ còn lệch
khi kết hợp keyword + `in_stock` (hoặc product ngoài `dateAvailable`) — closure
DB vẫn có thể bỏ bớt item sau khi Meili đã phân trang. Category/filter_value
KHÔNG còn gây lệch.

**Re-sync khi relation đổi**: `ProductWriteService::save`/`bulkUpdate` wrap
transaction trong `Product::withoutSyncingToSearch()` (auto-sync trên `saved`
chạy TRƯỚC syncCategories/syncFilters → doc stale, lại nằm TRONG transaction)
rồi gọi `syncSearchIndex()` SAU commit → `$product->searchableAllLocales()`
(nuốt exception + logError, không phá save flow). Mutation product ngoài
write service (seed CLI, SQL tay) → doc Meili stale tới lần import kế.

### `sortMap()` — single source of truth

`ProductRepository::sortMap()` declare TỪNG TOKEN với 3 mặt:
- `db` → `Spatie\QueryBuilder\AllowedSort` (cột DB hoặc callback scope) cho pipeline DB
- `meili` → tên attribute trong Meilisearch index, hoặc `null` nếu không index được
- `menu` → bool, có xuất hiện ở dropdown UI hay không

`allowedSorts()`, `sortMenu()`, `searchViaMeilisearch::sort` đều đọc từ map này
→ thêm sort mới = thêm 1 row, không drift giữa 2 engine. Token `created_at`
phải lên đầu vì `HasListFilterToolbar` dùng phần tử đầu của `sortMenu()` làm
default selection khi URL chưa có `?sort=`.

`null` ở `meili` → khi user search Meilisearch, sort đó bị bỏ qua âm thầm,
Meilisearch fallback ranking theo relevance score. KHÔNG ném error. (`name`
đã index + sortable từ 2026-07-13 — nhưng lưu ý Meili sort là ranking rule:
có keyword thì relevance vẫn tham gia, KHÔNG A→Z tuyệt đối như ORDER BY.)

### `config/scout.php` — index-settings (per-locale từ 2026-07-13)

File CỐ Ý KHÔNG `new Product` để lấy `searchableAs()` — config load TRƯỚC khi
service provider boot, instantiate Eloquent model trigger trait `Searchable` +
`Auditable` cần `view` service chưa register → `ReflectionException: Class "view"
does not exist`. Key `index-settings` build bằng loop `products_{locale}` đọc
THẲNG env `APP_LOCALES` (không dựa `config('app.locales')` — file config load
độc lập). `scout:sync-index-settings` tự prepend `SCOUT_PREFIX` cho key thường
→ push đủ settings cho mọi index per-locale trong 1 lệnh.

4 vai trò Meilisearch độc lập, một attribute có thể có 0/1/nhiều vai trò:

| Khai báo | Vai trò |
|---|---|
| `toSearchableArray()` model | Field nào được lưu trong document |
| `searchableAttributes` (config) | Field tham gia FTS ranking |
| `filterableAttributes` (config) | Field xài được trong `filter=` |
| `sortableAttributes` (config) | Field xài được trong `sort=` |

Mỗi attribute thêm vào filter/sort = thêm index riêng → tốn RAM + chậm write.
Chỉ khai báo capability thực sự dùng.

### Quy trình thêm field mới vào index

1. Thêm vào `Product::toSearchableArray()` → đẩy data lên doc.
2. Thêm vào `searchableAttributes`/`filterableAttributes`/`sortableAttributes`
   tương ứng trong `config/scout.php`.
3. `php artisan config:clear`
4. `php artisan scout:sync-index-settings` — push settings cho MỌI index
   per-locale (KHÔNG re-index doc).
5. `php artisan products:scout-import` — wrapper loop `config('app.locales')`,
   setLocale rồi delegate `scout:import` cho từng index `products_{locale}`
   (5-15 phút/locale trên 500k doc, chunk `SCOUT_CHUNK_SEARCHABLE=500`).
   Flags: `--locale=vi` (1 locale/process — split-process pattern khi nhiều
   locale + sợ OOM), `--fresh` (scout:flush trước, dọn doc mồ côi).
   KHÔNG chạy `scout:import` trần — chỉ đẩy index của locale đang active.
   Chạy với `SCOUT_QUEUE=false` (default) — queue worker có locale riêng
   → doc rơi sai index.

Bỏ qua step 5 = doc cũ KHÔNG có field mới → filter/sort theo field đó luôn rỗng.

### Verify Meilisearch settings

```
curl -s "http://localhost:7700/indexes" -H "Authorization: Bearer $MEILISEARCH_KEY" | jq '.results[].uid'
curl -s "http://localhost:7700/indexes/products_vi/settings" -H "Authorization: Bearer $MEILISEARCH_KEY" | jq '.filterableAttributes, .sortableAttributes'
```

phải thấy 1 index `products_{locale}` cho MỖI locale trong `APP_LOCALES`, mỗi
index đủ các field đã khai báo. Thiếu settings → chưa chạy
`scout:sync-index-settings`; thiếu index → chưa chạy `products:scout-import`.

### `positiveIntList` helper — trust shape or skip

`private static function positiveIntList(mixed $value): array` trong
`ProductRepository` chuẩn hoá input "danh sách id" từ request:
- `!is_array($value)` → return `[]` (skip filter, KHÔNG cố parse scalar/object).
  Form HTML đúng convention luôn gửi array; URL sai shape ≠ lỗi server, chỉ skip.
- Array → `array_map('intval')` + `array_filter(fn $v > 0)` + `array_values()`.

Triết lý chung cho list page (GET browse): KHÔNG validate-or-422 như FormRequest
POST mutation. Sai filter = ignore, vẫn render danh sách đầy đủ.

## Sidebar danh mục — accordion cha–con (2026-06-30)

`resources/web/views/category/structure/_side_bar_node.blade.php` — partial đệ
quy render 1 node trong cây, accordion dọc + boxed pill mỗi item.

### Lý do KHÔNG dùng class theme `widget-category-2`

Theme `public/web/sass/layout/_sidebar.scss` có rule:
```scss
.widget-category-2 ul li {
    display: flex;
    justify-content: space-between;
    border: 1px solid; padding: 9px 18px;
}
```
áp dụng cả children — biến submenu lồng thành box floating ngang → tràn ra ngoài
sidebar. Wrapper `_side_bar.blade.php` cố ý chỉ giữ `sidebar-widget` (không có
`widget-category-2`), tree dùng Tailwind thuần.

### Build tree từ flat list

`$categories` từ `Controller::buildDataCommon()` là flat `Collection<CategoryDTO>`
với `parent_id`. Block `@php` trong `_side_bar.blade.php` build:
- `$byParent`: map `parent_id → Collection<CategoryDTO>` cho lookup con
- `$byId`: map `id → CategoryDTO` để trace ancestors
- `$openIds`: set `parent_id → true` cho chuỗi tổ tiên của active node, để node
  cha auto-expand ở first render (KHÔNG chờ Alpine boot rồi mới expand).

Partial node nhận `byParent`, `openIds`, `activeId`, `depth` và recurse.

### Rotate icon — inline `:style` thay vì `rotate-90` class

Dự án dùng Tailwind v4 với `@tailwindcss/vite` (JIT scan blade ở build time).
Bundle CSS hiện tại trong `p

---

## TODO (2026-07-13): Meilisearch product search — filter trong closure KHÔNG áp cho Meili (pagination/recall sai)

**Bối cảnh:** `ProductRepository::searchViaMeilisearch()` là HYBRID Meili + DB, KHÔNG thuần Meili.

**Cơ chế (đã trace vendor):** `Builder::paginate()` (`vendor/laravel/scout/src/Builder.php:488`) — biểu thức lồng nhau: đối số trong `$engine->paginate()` (HTTP Meili) chạy TRƯỚC → trả trang ID + `totalHits`; rồi `$engine->map()` chạy SAU → `Searchable::queryScoutModelsByIds()` (`Searchable.php:329` `call_user_func($builder->queryCallback,$query)`) → `whereIn(id)->get()`. `total` = `getTotalCount($rawResults)` = `totalHits` của Meili.

- **Meili lo** (trên Scout builder, TRƯỚC dòng 258 của ProductRepository): keyword, `manufacturer_id`, `min/max_variant_price`, sort, paginate.
- **DB lo** (trong `$builder->query(fn (Builder $qb) => ...)`, dòng 262–300): `whereHas('productCategories')` (category_id), `whereHas('productFilters')` (filter_value_id), `whereExists` product_variant+product_stock (in_stock), `dateAvailable`, eager-load. Closure chạy SAU khi Meili đã phân trang → **KHÔNG áp cho Meili**.

**Hệ quả khi có filter closure (category / filter_value / in_stock):**
1. Trang thiếu (< perPage) — Meili trả 20 ID, DB bỏ bớt.
2. `total` + số trang SAI — dùng `totalHits` chưa trừ closure filter (over-count).
3. Recall thấp (tệ nhất) — sản phẩm đúng filter nhưng không lọt top-N Meili → không bao giờ hiện.
(Sản phẩm HIỆN ra thì vẫn đúng — precision OK; chỉ thiếu/sai đếm.)

Chỉ SAI khi dùng closure filter. Nếu chỉ lọc bằng thứ Meili biết (keyword/manufacturer/price/sort) → chính xác tuyệt đối. Design cũ cho browse (luôn có category/filter) đi thẳng DB là **có chủ ý** vì DB làm 3 việc đó chuẩn. Hiện `list()` đã bị sửa cho browse cũng vào Meili → **làm lộ bug này ở trang danh mục**.

**Tiến độ (cập nhật 2026-07-13):**
1. ✅ **category_id, filter_value_id → Meili** — ĐÃ LÀM:
   - `Product::toSearchableArray()`: thêm 2 mảng int từ pivot (`loadMissing` để searchableAllLocales không re-query mỗi vòng).
   - `config/scout.php`: thêm vào `filterableAttributes`.
   - `searchViaMeilisearch()`: `whereIn` trên SCOUT builder; ĐÃ BỎ `whereHas` khỏi DB closure (closure chỉ còn eager-load productFilters để hiển thị).
   - `makeAllSearchableUsing()`: eager-load `productCategories`, `productFilters`.
   - BONUS: `ProductWriteService::save`/`bulkUpdate` wrap `withoutSyncingToSearch` + re-sync `searchableAllLocales()` SAU commit (auto-sync trên `saved` chạy trước syncCategories/syncFilters → stale; xem mục searchViaMeilisearch).
2. ⬜ **in_stock** — CHƯA. Thiết kế đã chốt sau khi trace code (2026-07-13), xem
   mục "Thiết kế `has_stock` — batch sync qua stock_movement watermark" bên dưới.
   Hiện in_stock vẫn ở DB closure → total/paging còn lệch khi combine keyword + in_stock.
3. ✅ **name (đa ngôn ngữ)** — ĐÃ LÀM: `searchableAs()` = `'products_'.app()->getLocale()` (kèm `SCOUT_PREFIX`), `config/scout.php` build index-settings per-locale từ env `APP_LOCALES`, `sortMap()['name']['meili']` = `'name'` + `name` vào `sortableAttributes`. Model có thêm `searchableAllLocales()`/`unsearchableAllLocales()`.
4. ⬜ `pagination.maxTotalHits` mặc định 1000 — nâng nếu duyệt sâu danh mục lớn.
5. ✅ **Lệnh sync/import** — command mới `products:scout-import` (`app/Console/Commands/ScoutImportProductsCommand.php`) loop locale + delegate `scout:flush`/`scout:import`. Quy trình chuẩn: `config:clear` → `scout:sync-index-settings` → `products:scout-import` (xem "Quy trình thêm field mới vào index").

**Việc còn nợ cluster này:**
- Xoá product (destroy) chưa gọi `unsearchableAllLocales()` — Scout auto chỉ gỡ doc ở locale hiện tại; các index locale khác còn doc mồ côi tới lần `products:scout-import --fresh`.
- Mutation ngoài write service (seed CLI, `DB::table` bulk) không re-sync Meili — giống drift #7 của cache observer; chạy `products:scout-import` sau seed.
- Index cũ `products` (không suffix locale) còn trên Meilisearch server — xoá tay: `curl -X DELETE "$MEILISEARCH_HOST/indexes/products" -H "Authorization: Bearer $MEILISEARCH_KEY"`.

**Lựa chọn an toàn tạm thời nếu browse có vấn đề:** rollback `list()` về `keyword === '' → DB` để browse không vỡ (chỉ còn cần cho case in_stock).

### Thiết kế `has_stock` — batch sync qua `stock_movement` watermark (chốt 2026-07-13, CHƯA implement)

Đánh giá đề xuất gốc ("observer trên ProductStock lật `has_stock` ngay"):
đúng hướng denormalize nhưng có 3 điểm yếu khi soi code thật:

1. **Churn nằm ở `reserved`, không phải `on_hand`.** Semantic in_stock =
   `policy bypass OR (on_hand - reserved) > 0`; `reserved` đổi ở MỌI
   add-to-cart (`StockService::reserveOne`) + mọi TTL release
   (`stock:release-expired` cron mỗi phút). Sản phẩm sắp hết hàng lật qua
   lật lại theo vòng reserve→release 15 phút → observer lật ngay = bão update.
2. **Observer chạy TRONG transaction checkout đang giữ lock.**
   `reserveOne`/`deductForOrder` đều `lockSellableStocks` (lockForUpdate)
   trong transaction. UPDATE `product` + HTTP Meili tại đó = kéo dài lock,
   reintroduce contention trên row `product` (đúng cái contention mà schema
   tách `product_stock` ra để tránh), Meili down làm chậm/lỗi đường tiền.
3. **Đẩy qua Scout `searchable()` quá nặng cho 1 field** — re-push full doc
   (query description + categories + filters) × N locale chỉ vì 1 boolean.

**Thiết kế thay thế** — tận dụng 2 thứ có sẵn: `stock_movement` là changelog
append-only của mọi biến động tồn + scheduler đã chạy `everyMinute`:

1. **Vá bug audit TRƯỚC (nên làm bất kể):** `ProductStockRepository::updateOnHand`
   (CMS bulk edit) sửa `on_hand` mà KHÔNG ghi `stock_movement` — phá invariant
   "rebuild được on_hand từ SUM movement". Thêm movement type `Adjust` tại đây.
   Sau vá, mọi biến động tồn đều có movement → dùng được làm changelog.
   (`consumeAllHolderReservations` không ghi movement nhưng chỉ chạy nhánh
   Untracked — policy bypass nên `has_stock=1` bất biến, an toàn.)
2. **Cột `product.has_stock TINYINT(1) NOT NULL DEFAULT 1`** + command
   `stock:sync-has-stock` schedule mỗi phút (`withoutOverlapping`):
   - Watermark = `stock_movement.id` cuối đã xử lý, lưu bảng `setting`.
   - `SELECT DISTINCT pv.product_id FROM stock_movement sm JOIN product_variant pv ...
     WHERE sm.id > watermark` → recompute EXISTS (đúng query của filter hiện tại)
     cho từng product → **flip-only**: chỉ UPDATE khi giá trị đổi.
3. **Đẩy Meili bằng partial document update, KHÔNG qua Scout:** gom mọi product
   lật trong batch → 1 call `POST /indexes/products_{locale}/documents` với
   `[{id, has_stock}, ...]` per locale (Meili tự merge partial doc).
   N flips × M locales = M HTTP call, không phải N×M.
4. `toSearchableArray()` đọc cột + thêm `has_stock` vào `filterableAttributes`;
   `searchViaMeilisearch()` chuyển in_stock từ DB closure lên
   `$builder->where('has_stock', ...)` → hết pagination bug. Filter
   `AllowedFilter::callback('in_stock')` ở DB pipeline cũng đổi sang cột luôn —
   2 engine cùng semantic + nhanh hơn correlated EXISTS trên 500k row.
5. Riêng CMS tạo/sửa variant: `ProductVariantWriter::sync` recompute `has_stock`
   inline (tần suất thấp; stock row mới tạo chưa chắc có movement).

Lợi ích so với đề xuất gốc: hot path checkout ZERO cost (không observer, không
HTTP, không product lock); cron 1 phút tự thành debounce (lật qua lật lại trong
phút = 0 update ròng); staleness tối đa ~1 phút — chấp nhận được cho filter
search. Trade-off: seeder bulk (drift #7) vẫn cần `products:scout-import` sau
seed như mọi khi.

**Stopgap rẻ nhất nếu chưa làm:** request có `filter[in_stock]` + keyword →
route về DB pipeline (fallback LIKE) — đúng tuyệt đối nhưng chậm 1-3s.

---

## Hạ tầng scale 30k + chống over-redeem toàn tuyến checkout (2026-07-15)

### Hạ tầng — trỏ file, không lặp

- Checklist + changelog: `docs/SCALE-30K.md`. Staging mô phỏng prod:
  `docker-compose.scale.yml` (Redis tách 2 instance, MariaDB 1 master + 2
  replica GTID tự seed qua `docker/mysql/replica-init.sh`, ProxySQL route
  6033 write / 6034 read, scheduler container). Prod: toàn bộ config + bước
  triển khai trong `deploy/` (README là bản đồ).
- `config/database.php`: cache connection nhận `REDIS_CACHE_HOST/PORT/PASSWORD`,
  mysql nhận `DB_READ_PORT`/`DB_WRITE_PORT` — TẤT CẢ fallback về biến cũ,
  không set env mới thì chạy y như cũ. Đừng phá tính fallback này.
- Throttle: named limiter `throttle:add-to-cart` / `throttle:save-order`
  (định nghĩa `AppServiceProvider::registerRateLimiters`, mức ở
  `config/throttle.php`, nới qua env khi k6). Key theo user → session → IP,
  KHÔNG thuần IP; đi kèm `trustProxies` trong `bootstrap/app.php` — thiếu nó
  thì sau LB cả site chung 1 quota theo IP của nginx.

### Mẫu chuẩn chống over-redeem/oversell — "guard trong chính câu UPDATE"

Mọi tài nguyên đếm được (tồn kho, lượt coupon, suất gift, số dư voucher,
điểm thưởng) dùng CÙNG một mẫu, KHÔNG check-then-act:

1. **Conditional UPDATE**: điều kiện còn-suất nằm ngay trong WHERE, trả
   affected rows. 0 = hết đúng thời điểm chốt. Vd
   `CouponRepository::incrementUsedCount` (`used_count + ? <= uses_total`),
   `VoucherRepository::incrementRedeemed` (`redeemed + ? <= amount`),
   `GiftRepository::incrementUsedCount`. Dùng `DB::table` chứ không Eloquent
   instance (read-modify-write là chính cái race đang diệt; tránh scope ẩn +
   updated_at + observer chạy trong lúc giữ lock).
2. **Hết suất thì tùy domain**: tiền sai → THROW rollback cả đơn
   (`InsufficientStockException`, `CouponExhaustedException`,
   `RewardExhaustedException`, `VoucherExhaustedException` — catch chain trong
   `CheckoutController::saveOrder`, mỗi loại một message i18n). Gift hết suất
   → BỎ quà, đơn vẫn chạy (`droppedGifts` trên `CheckoutPromotions` → cảnh
   báo trang success) — hết quà không đáng chặn doanh thu.
3. **Guard per-user / per-balance cần đếm**: locking read (`lockForUpdate`)
   SAU khi đã giữ X-lock row cha — locking read đọc bản committed mới nhất
   (không phải snapshot REPEATABLE READ). Vd coupon
   `countUsedByUserForUpdate` sau incrementUsedCount; reward
   `recordRedeem` SUM points FOR UPDATE (cùng điều kiện `getTotalPoints`).

### Thứ tự lock TOÀN CỤC trong transaction tạo đơn

`stock → coupon → voucher → gift → reward` — mọi transaction (kể cả
revert/hủy đơn, `PromotionService::revertForOrder`) phải đi CÙNG chiều.
Thêm lock mới = nối vào cuối chuỗi, cập nhật comment trong
`CreateOrderService::create`.

### StockService — release hold idempotent (fix race 2026-07-15)

- **"Ai xóa được hold, người đó mới được trừ reserved"**:
  `deleteReservationById()` trả affected rows làm khóa idempotent — mọi nơi
  nhả/tiêu thụ hold (applyDeduction, consumeAllHolderReservations,
  releaseReservationRow) chỉ trừ `reserved` khi delete affected = 1. Không
  có nó: job release đua với checkout → double-decrement reserved →
  `holderAvailable` phình → on_hand âm cả khi Deny + bật kiểm tồn.
- `releaseReservationRow`: lock stock trước → RE-FETCH reservation fresh dưới
  lock (row caller đưa vào chỉ là GỢI Ý — có thể đã bị đổi quantity/gia hạn) →
  `releaseExpired` truyền `onlyIfExpired: true` (không nhả hold khách vừa
  gia hạn).
- `on_hand` âm giờ CHỈ còn 2 đường chủ ý: policy Backorder và tắt
  `config_stock_checkout`. CMS ghi tồn clamp ≥ 0 + đúng kho mặc định
  (`ProductVariantWriter`, `updateOnHand(?int $warehouseId)`).

### Transaction create order — thin core / lock late / retry

`CreateOrderService::create` cấu trúc 3 vùng, GIỮ NGUYÊN khi sửa:

1. Insert thuần (order, history, items, totals) — không lock.
2. Vùng lock cuối transaction (stock → promotion → reward redeem) — giữ lock
   ngắn nhất; throughput hot row = 1/thời-gian-giữ-lock.
3. `DB::afterCommit`: side effect dẫn xuất (earn điểm, affiliate) — mỗi hàm
   PHẢI tự nuốt exception (try/catch + logError). afterCommit chạy đồng bộ
   TRONG lời gọi create() → throw ở đó rơi vào catch của saveOrder = trang
   lỗi giả cho đơn đã commit + phá idempotency cache. Riêng
   `writeRewardRedeem` (TIÊU điểm) là tiền → ở lại transaction.

`transaction($closure, attempts: 3)` — deadlock/lock-wait-timeout tự retry
(BaseRepository::transaction có param `$attempts`). Closure phải re-run an
toàn: mọi ghi trong transaction, không side effect ngoài DB trước điểm fail.

### saveOrder — 2 giai đoạn, ranh giới là COMMIT

- **Giai đoạn 1 (được phép fail)**: chỉ `create()` + ghi idem cache. Các
  catch nghiệp vụ trả trang lỗi (đơn chưa tồn tại, rollback sạch).
- **Giai đoạn 2 `finalizeCreatedOrder` (không bao giờ fail ra ngoài)**: dọn
  cart/session TRƯỚC (đơn tồn tại mà cart còn + token đổi = khách đặt lại ra
  đơn đôi thật), rồi email, payment — lỗi chỉ log + gom vào `$warnings` hiển
  thị trên trang success (`successRedirect()`; success.blade có render flash
  `failed` dạng alert-warning). Gateway lỗi ≠ trang lỗi.
- **Idempotency**: token sinh ở trang checkout (`currentIdempotencyToken` —
  đọc session, trống mới sinh); saveOrder CHỈ ĐỌC token (request → session →
  fallback chữ ký giỏ), tuyệt đối không gọi hàm sinh — sinh ở nơi tiêu là tự
  phá khóa. Check `Cache::add` TRƯỚC khi tính giỏ (double-click không tốn
  compute); `buildCartForOrder(): array|RedirectResponse` + memoize `??=`;
  fail sau khi giữ lock phải `Cache::forget`. Lớp 2 = DB UNIQUE
  `orders.idempotency_key`.
- **`upsertOrder` contract**: id trong $data do SERVER xác định (0 = tạo mới,
  hoặc id đã qua ownership check như `getOrderForUser`). Checkout ép cứng
  `'id' => 0` trong buildOrderRow. KHÔNG BAO GIỜ đưa id từ client input vào.

### Voucher — reserve-at-order (phương án A, 2026-07-15)

Số dư voucher hành xử như tồn kho: TRỪ NGAY trong transaction tạo đơn
(conditional `incrementRedeemed`), history ghi thẳng `Confirmed`, hết dư →
`VoucherExhaustedException` rollback đơn. `confirmOrderVouchers` ĐÃ XÓA
(trước đó mồ côi — không ai gọi nên số dư chưa bao giờ bị trừ; rows `Applied`
cũ trong voucher_history là dữ liệu lịch sử chưa trừ, cần CS đối soát nếu
quan tâm). Đơn hủy: `revertOrderVouchers` decrement rows Confirmed +
reactivate — giữ nguyên.

### Enum + i18n cho promotion — nguồn sự thật

- **Giá trị nghiệp vụ ở `app/Enums/`** (KHÔNG còn ở config core):
  `CouponType`, `CouponApplyScope`, `CouponHistoryStatus`, `GiftTriggerType`,
  `GiftPickType`, `VoucherStatus`, `VoucherHistoryStatus`. Style chung:
  int-backed + `fromInput(mixed): ?self` + `label(): string` qua `trans()`.
  Config core `coupon/gift/voucher` chỉ còn cache key + công tắc hành vi
  (`stacking`) + TTL.
- **Chữ hiển thị ở `messages.checkout.{coupon,gift,voucher,reward}`** —
  template TRỌN CÂU cho sprintf (không nối chuỗi — dịch ngôn ngữ khác đảo
  trật tự từ); `%` literal phải escape `%%`. So sánh dùng
  `Enum::Case->value`, label dùng `Enum::fromInput($x)?->label() ?? trans(fallback)`.
- **Gotcha trùng key lang**: PHP array literal trùng key = key sau thắng
  LẶNG LẼ (đã dính `checkout.gift` string vs group). Thêm key mới vào group
  phải soát key cùng tên; script đếm nhanh:
  `re.findall(r"'([a-z_]+)'\s*=>", block)` + Counter.

### Setting `cms_public` + endpoint public `system/init`

`GET /rcms/system/init` là PUBLIC (trước auth) — chỉ trả setting có
`setting.cms_public = 1` (`SettingRepository::listPublicCached`, cache key
riêng, flush chung trong `flushCache`). Key vận hành nguy hiểm
(`config_stock_checkout`) migration đã set 0. SystemController còn lưới cuối
chặn pattern credentials. Admin ẩn thêm key = tắt cờ, không sửa code.

### Ảnh — 2 fix nhỏ dễ quên

- `images:migrate-r2` gom path từ 4 bảng: `product`, `product_image`,
  `product_variant`, `option_value` — thêm nguồn ảnh mới nhớ nối vào đây,
  không thì thumbnail không được pre-gen → 404 CDN (disk remote không stat).
- `FileController::disk()` theo `config('media.image_disk')` (env
  `IMAGE_DISK`), KHÔNG hardcode 'public' — multi-server thì file gốc phải lên
  storage chung ngay lúc upload.

