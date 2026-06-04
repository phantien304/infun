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
