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
- API trait:
  - `rememberCache($key, $resolver, $ttl = null, $perLocale = true)` /
    `forgetCache($key, $perLocale = true)` — cache thường, chạy với mọi driver.
  - `rememberCacheTagged($tags, $key, $resolver, $ttl = null, $perLocale = true)` /
    `forgetCacheTagged($tags)` — cache theo tag. Driver hỗ trợ tag (redis, memcached)
    cache theo tag để flush được cả nhóm; driver khác (file, database) tự fallback
    về `rememberCache` thường.
- `perLocale = true` tự nối `app()->getLocale()` vào cuối key. Nếu key còn phụ thuộc
  user group / user type / limit / ids thì tự đưa vào key trước khi gọi.
- Mọi cache liên quan **giá hiệu lực** (sản phẩm + special) PHẢI gắn `getUserGroupId()`
  vào key, vì giá hiệu lực thay đổi theo nhóm khách hàng. Quên = user nhóm này thấy
  giá user nhóm khác.
- **Cache lưu Entity, KHÔNG lưu DTO.** Repo (vd `listAllCached`, `getProductLatest`) trả
  `Collection<Model>`; controller convert DTO sau khi đọc cache. DTO là pure transform
  trên model nên gọi sau cache không phá invariant.

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

- Định nghĩa: `effective_price = COALESCE(special.price, product.price)`, trong đó
  `special` là row `product_special` priority cao nhất đang active (đúng `user_group_id`,
  date trong `[date_start, date_end]`).
- 2 scope trên `Product` model:
  - `scopeEffectivePriceBetween(?int $min, ?int $max)` — filter giá hiệu lực trong
    khoảng. Truyền `null` cho 1 đầu để bỏ ràng buộc tương ứng.
  - `scopeOrderByEffectivePrice(string $dir)` — sort theo giá hiệu lực.
- 2 scope đều dùng chung SQL fragment từ `protected static effectivePriceExpression()`:
  correlated subquery scalar `SELECT ps.price ... ORDER BY priority DESC LIMIT 1`,
  wrap trong `COALESCE(..., product.price)`. Filter & sort luôn nhất quán cùng 1 nguồn.
- **Bắt buộc có index** trên `product_special`:
  `(product_id, user_group_id, priority, date_start, date_end)`. Không có index = N×M
  table scan, list sản phẩm treo.
- Tận dụng relation `Product::productSpecial()` (đã là `hasOne ofMany` với `priority MAX`
  + `dateStartToEnd` + `getUserGroupId()`) để eager-load row đại diện cho DTO; KHÔNG
  query thủ công lại logic này ở repo.

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
- `clientQuery()` (helper cho hot resources `getProductLatest/Feature/Related`)
  KHÔNG order mặc định — mỗi caller tự append `orderBy` phù hợp semantic. Tránh hardcode
  `orderBy('id', 'DESC')` ở base.
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
   Mirror đúng `getProductFeature()`, tái dùng `clientRelations()`.
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
