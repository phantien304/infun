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
  user group / user type / limit thì tự đưa vào key trước khi gọi.

## DTO — tầng output

- DTO ở `app/Data/Output/*DTO`, dùng Spatie Laravel Data (`extends Data`).
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
  nếu không sẽ N+1 âm thầm.

## Model — quan hệ & scope

- Scope dùng chung ở trait `App\Models\Traits\HasAdvancedScopes`: `forLocale($table = '')`
  (lọc `language_code` theo locale; truyền tên bảng khi query có join), `dateStartToEnd`,
  `dateAvailable`.
- Quan hệ `description()` là `hasOne(...Description::class)->forLocale()` — tự lọc locale.
- Cần đúng MỘT bản ghi liên quan theo tiêu chí (vd special ưu tiên cao nhất): dùng
  `hasOne(...)->ofMany([...], $closure)`, không dùng hasMany rồi `->first()`.
- Repository list muốn filter/sort theo cột bảng dịch (`*_description.title`) phải tự
  join bảng đó trong `baseQuery()`.

## List / phân trang / sort / filter — `QueryableRepository`

- Repository list mới extends `App\Repositories\Base\QueryableRepository`, chạy trên
  **Spatie QueryBuilder**. Cấu hình bằng cách override: `allowedFilters()`,
  `allowedSorts()`, `defaultSort()`, `allowedIncludes()`, `withRelations()`, `baseQuery()`.
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
- Blade sinh link sort/per_page phải emit đúng param `sort` / `per_page`, và reset
  `page` khi đổi (dùng `request()->except('page')`).

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
  interface của repo (vd `StoreReviewRepositoryInterface`).

## Phân trang ở blade — partial `_paging`

- Partial `web.share.structure._paging` nhận `$paginator`, build link qua
  `$paginator->appends(request()->query())`. Laravel KHÔNG có method `removeQuery` —
  muốn loại key thì truyền `removeKey` (mảng) vào `links()`, partial dùng `Arr::except()`
  (hỗ trợ dot notation cho filter lồng, vd `filter.category_id`). `appends()` tự bỏ
  qua key `page`.

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

`ProductController` hiện vẫn theo style cũ, chưa migrate xong — không dựa vào nó
làm mẫu.

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
2. `ProductRepository::getProductLatest()` — query thật + cache qua `rememberCache()`.
   Mirror đúng `getProductFeature()`, tái dùng `clientRelations()`.
3. Khai báo trong `ProductRepositoryInterface`.

Relation `Product::description()` đã tự áp `->forLocale()`, không cần lọc locale thủ công.

## Ảnh

Dùng `intervention/image` v3 — không có facade `Image`. Trong `MyStorage::resizeImage`:
khởi tạo `ImageManager::gd()`, đọc `->read()`, resize `->coverDown()`, encode
`->encodeByPath()`. Ảnh mặc định (`no_img`) nằm trong `public/` — trả URL bằng `asset()`,
không qua storage disk (disk `public` sẽ chèn `/storage` vào đầu).
