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
- API trait: `rememberCache($key, $resolver, $ttl = null, $perLocale = true)` và
  `forgetCache($key, $perLocale = true)`.
- Trait dùng `Cache::remember()` thuần — **không dùng tags** → chạy với mọi driver
  (file, database, redis). Đổi lại: invalidate phải qua `forgetCache` theo key,
  không flush được theo nhóm tag.
- `perLocale = true` tự nối `app()->getLocale()` vào cuối key. Nếu key còn phụ thuộc
  user group / user type / limit thì tự đưa vào key trước khi gọi.

## Cũ vs Mới (đang migrate)

| | Cũ (legacy) | Mới |
|---|---|---|
| Repository | `App\Repositories\Client\InfunStudio\*` | `App\Repositories\Eloquent\*` |
| Model | `App\Model\Entities\*` (số ít) | `App\Models\Entities\*` (số nhiều) |
| Method | tiền tố `_` (`_to`, `_processMetaSeo`) | không tiền tố |
| Cache | `Cache::tags()` + redis cứng | `CacheableRepository` |

`ProductController` hiện vẫn theo style cũ, chưa migrate xong — không dựa vào nó
làm mẫu.

## Helper toàn cục thường dùng

`getCoreConfig()`, `getModuleConfig()`, `getConfigDb()`, `getUserGroupId()`,
`getUserType()`, `getUserLoginId()`.

## Mẫu tham chiếu: `getProductLatest`

Luồng chuẩn cho dữ liệu dùng chung + có cache:

1. `Controller::getProductLatest(int $limit = 6)` — facade gọn, chỉ delegate
   `$this->productRepo->getProductLatest($limit)`.
2. `ProductRepository::getProductLatest()` — query thật + cache qua `rememberCache()`.
   Mirror đúng `getProductFeature()`, tái dùng `buildWithRelationProduct()`.
3. Khai báo trong `ProductRepositoryInterface`.

Relation `Product::description()` đã tự áp `->forLocale()`, không cần lọc locale thủ công.
