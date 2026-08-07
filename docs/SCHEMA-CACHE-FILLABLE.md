# `HasSchemaCache` — cập nhật audit + quy ước `$fillable` cho model mới (2026-08-03)

> Bổ sung cho mục "Audit Base traits — Cần review trước khi action (2026-06-11)"
> trong `CLAUDE.md`. Đọc mục đó trước — file này chỉ note phần đã đổi/mới phát
> hiện từ 2026-08-03, không lặp lại toàn bộ.

## Sự cố khởi phát

Convert module `banner` (mt219 → CMS React) thêm 3 cột (`media_type`,
`video_provider`, `video_url`) vào `banner_value` qua migration. Sau khi chạy
migration, `save()` cho `BannerValue` **không lỗi** nhưng 3 cột mới **không
bao giờ ghi xuống DB** — vì `HasSchemaCache::getFillable()` (khi model không
khai báo `$fillable`) cache **vĩnh viễn** (`cache()->forever()`) danh sách cột
lấy từ `DESCRIBE`, cache này lập trước migration nên không biết cột mới tồn
tại. Phải `cache()->forget()` đúng key `schema:{db}:{driver}:{table}` mới hết.

## Số liệu cập nhật (thay cho "135/136" trong audit 2026-06-11)

- Tổng **151 model** entity hiện tại.
- **136/151 (~90%)** không khai báo `$fillable` thật → vẫn đi qua nhánh
  fallback-toàn-bộ-cột của trait.
- Trong 15 model "tưởng đã khai báo": chỉ **4** thật sự có `$fillable`
  (`StockTransferItem`, `StockTransfer`, `StockMovement`, `StockReservation`).
  **11** còn lại chỉ set `protected $guarded = [];` — trait
  `HasSchemaCache::getFillable()` chỉ kiểm tra `parent::getFillable()` (tức
  property `$fillable`), **không đọc `$guarded`** → 11 model này (`Coupon`,
  `Gift`, `Voucher`, `VoucherHistory`, `CouponHistory`, `OrderGift`,
  `GiftTriggerProduct`, `GiftItem`, `UserCoupon`, `VoucherRewardRule`,
  `VoucherRewardGrant`) **vẫn hit fallback/cache y như chưa khai báo gì**.

## Correction cho chính audit 2026-06-11

Audit cũ nghi cột `updated_at` do `HasSchemaCache::getFillable()` tự chèn vào
fillable có thể là cơ chế đang được dùng ngầm. Đã verify runtime hôm nay:
`getSystemConfig('updated_at_column.field')` trả **`null`** (đúng như audit
2026-06-11 đã ghi ở mục "Lỗi config — `system.updated_at_column.field`
thiếu") → nhánh `if ($updatedAt) { $fields[] = $updatedAt; }` **không bao giờ
chạy**. Xác nhận: **không có** cơ chế ẩn nào phụ thuộc field `updated_at`
trong trait này. Model `$timestamps = true` (128/151) đã được Eloquent tự
quản lý `updated_at` độc lập hoàn toàn với trait.

## Rủi ro mới: Octane/RoadRunner

`composer.json` có `laravel/octane` (không phải `require-dev`), kèm
`.rr.yaml` + `config/octane.php` đã wire đầy đủ — tức persistent worker là
mục tiêu kiến trúc thật (dù staging/prod Docker hiện tại (`docker/php/
Dockerfile.staging`) chạy `php-fpm` thuần, chưa `octane:start`). Trait dùng
`protected static array $schemaCacheMemo` — **không bao giờ tự clear trong
vòng đời 1 process**. Dưới php-fpm vô hại (mỗi request = process mới). Dưới
Octane/RoadRunner (worker sống hàng nghìn request): dù Redis cache đã được
xoá đúng cách, **worker đang chạy vẫn tiếp tục dùng schema cũ từ memo tới khi
worker đó bị recycle/restart**. `THEME-SYSTEM.md` (mục 3 — Octane) đã ghi
nhận đúng class bug này xảy ra thật với theme state; chưa xảy ra với schema
cache (Octane chưa bật ở môi trường thật) nhưng cơ chế giống hệt.

## Quy ước mới — áp dụng ngay từ hôm nay

**Mọi model mới, hoặc model bị đụng cột (migration thêm/đổi field mass-assign
được), PHẢI khai báo `protected $fillable = [...]` thật** (liệt kê đúng tên
cột được phép ghi qua `save()`/`fill()`, loại `id`; `$guarded = []` **không**
tính vì không thoát được fallback của trait). Không cần lo về `updated_at` —
xem correction ở trên, không có gotcha nào cả.

Ví dụ vừa áp dụng cho cụm banner (`app/Models/Entities/Banner*.php`):

```php
// Banner.php
protected $fillable = ['position', 'page', 'type', 'sort_order'];

// BannerDescription.php
protected $fillable = ['banner_id', 'language_code', 'title'];

// BannerValue.php
protected $fillable = [
    'banner_id', 'link', 'sort_order', 'image',
    'media_type', 'video_provider', 'video_url',
];

// BannerValueDescription.php
protected $fillable = ['banner_value_id', 'language_code', 'title', 'content'];
```

## Lộ trình xoá hẳn trait (chưa làm — path đồng thuận)

Không xoá trait ngay — vẫn load-bearing cho ~90% model, xoá thẳng = vỡ mass
assignment toàn bộ (giữ nguyên verdict "GIỮ" của audit 2026-06-11). Đường đi
tăng dần, không cần 1 PR lớn:

1. Model mới/bị đụng → khai báo `$fillable` thật ngay (quy ước ở trên).
2. Chuyển 11 model đang `$guarded = []` sang `$fillable` liệt kê cột thật.
3. Khi cả 151 model có `$fillable` thật → xoá `HasSchemaCache` khỏi `Base`.
   Đã verify: `getTableColumnAndTypeList()` không còn caller nào khác ngoài
   chính trait (audit 2026-06-11 từng ghi 1 caller ở `ProductSpecialRepository`
   — file đó không còn tồn tại trong repo, ghi chú đó đã cũ).
4. Việc rẻ, làm bất kỳ lúc nào không cần chờ #1-3: thêm bước xoá cache
   `schema:*` vào `deploy/prod-deploy.sh` / `staging.sh` ngay sau
   `migrate --force`; và/hoặc đổi `cache()->forever()` thành TTL (vd 6h) làm
   lưới an toàn tự phục hồi nếu bước deploy quên xoá cache.

## Cập nhật 2026-08-03 — đã khai `$fillable` cho cụm Order

Áp dụng bước #1/#2 của lộ trình ở trên cho toàn bộ model `app/Models/Entities/
Order*.php` (đối chiếu cột thật qua migration + `CREATE TABLE` trong
`infun_xampp.sql`, cross-check với mọi `::create()`/`fill()` call site trong
`OrderRepository`, `OrderAdminWriteService`, `CreateOrderService`):

- **Đã khai `$fillable` thật** (10 model): `Orders`, `OrdersProduct`,
  `OrdersProductOption`, `OrdersStatus`, `OrdersTotal`, `OrdersStatusLog`,
  `OrdersStatusCarrierOrder`, `OrdersHistory`, `OrdersCancel`, và `OrderGift`
  (đổi từ `$guarded = []` — trait không đọc `$guarded` nên trước đó vẫn hit
  fallback y hệt chưa khai gì).
- **Phát hiện phụ, chưa sửa** — `OrdersStatusCarrierOrder::$primaryKey` khai
  `['carrier_order_status_id', 'language_code']` nhưng bảng thật KHÔNG có cột
  `language_code`. Cần review riêng (ngoài phạm vi việc khai fillable).
- ~~**Bỏ qua, cần hỏi lại chủ dự án** `OrdersVoucher`~~ → **ĐÃ XOÁ (2026-08-03)**,
  xem mục "Xoá bảng legacy `orders_voucher`" trong `docs/CLAUDE.md`. Model trỏ
  vào tên bảng `order_voucher` (thiếu `s`) không tồn tại; bảng thật
  `orders_voucher` có trong dump nhưng rỗng và mọi cột đều trùng `voucher`.
  Đã drop cả model lẫn bảng.

## Cập nhật 2026-08-03 (tiếp) — đã khai `$fillable` cho cụm Product

Áp dụng bước #1/#2 của lộ trình cho **17/18** model `app/Models/Entities/
Product*.php`. Không có `infun_xampp.sql` sẵn trong lần này (không tìm thấy
file dump trong repo) — đối chiếu bằng migration (đọc từng migration chạm tới
bảng, cộng dồn cột thêm/xoá qua thời gian) + cross-check mọi call site
`::create()`/property-assign trực tiếp trong `ProductWriteService`,
`ProductVariantWriter`, `ProductCmsRepository`, `ProductCategoryRepository`.

- **Đã khai `$fillable` thật** (17 model): `Product`, `ProductVariant`,
  `ProductVariantAttribute`, `ProductVariantDescription`,
  `ProductVariantSpecial`, `ProductVariantDiscount`, `ProductStock`,
  `ProductOption`, `ProductOptionValue`, `ProductCategory`, `ProductFilter`,
  `ProductRelated`, `ProductIngredient`, `ProductAttribute`, `ProductReward`,
  `ProductDescription`, `ProductImage`.
- **Bỏ qua, chưa khai** — `ProductDraft`: không có migration tạo bảng (base
  table không rõ nguồn), không có bất kỳ call site nào ghi (`ProductController::
  approve()` trả thẳng `501 Chưa hỗ trợ`, feature stub "làm sau"). Không đủ căn
  cứ để liệt kê đúng cột — khai sai còn tệ hơn không khai (âm thầm chặn field
  hợp lệ nếu sau này ai đó implement feature này bằng `fill()`/`create()`).
  Cần xác nhận cột thật (DESCRIBE hoặc dump) trước khi khai.
- **Cột cố ý LOẠI khỏi `$fillable` dù là cột thật trong DB** (khác với
  "không tìm thấy cột" — đây là loại chủ đích vì lý do an toàn mass-assignment):
  - `Product`: `min_variant_price`, `max_variant_price`,
    `max_variant_discount_percent`, `rating_avg`, `rating_sum`,
    `review_count`, `rating_distribution`, `rating_updated_at`, `viewed` —
    toàn bộ do observer tính/ghi (`ProductVariant::saved/deleted`, observer
    review), mass-assign từ input CMS sẽ ghi đè giá trị tính toán.
  - `ProductFilter`: `filter_id` — có TRIGGER DB (`trg_product_filter_bi`/
    `_bu`) tự derive từ `filter_value_id` mỗi INSERT/UPDATE; app chỉ cần gửi
    `product_id`+`filter_value_id`, trigger tự set, không nên mass-assign.
- **Phát hiện phụ, chưa sửa** (ngoài phạm vi khai fillable):
  - `product_description.slug` là cột thật (dùng trong `SeedProductsCommand`,
    đọc qua `resolveSlug()` ở storefront) nhưng
    `ProductCmsRepository::syncDescriptions()` hiện KHÔNG ghi cột này — CMS
    chưa có field cho phép admin đặt slug tuỳ chỉnh, sản phẩm tạo mới phụ
    thuộc hoàn toàn vào `resolveSlug()` tự sinh từ `name` lúc đọc (không
    persist). Cần review riêng nếu muốn cho phép override qua CMS.
  - `product.subtract` là cột legacy OpenCart, được đọc (`ProductData::
    fromModel()`) nhưng không còn caller nào ghi (đã thay bằng
    `product_stock.subtract` per-warehouse từ migration
    `unify_simple_product_stock`) — giữ trong `$fillable` vì là cột thật,
    nhưng thực tế đã là dead-write-path, chỉ còn ý nghĩa đọc fallback.
