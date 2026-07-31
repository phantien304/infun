# `app/Http/Controllers/Api/Cms` — controller CMS (API cho SPA infuncms)

Prefix URL `/rcms` (đặt ở `RouteServiceProvider::mapCmsApiRoutes`), route khai
báo trong `routes/rcms.php`.

Thư mục này sẽ phình từ 6 lên **~90 controller** khi CMS làm đủ cho 166 bảng.
Các thư mục con bên dưới là để 90 file đó không nằm phẳng một đống.

Xem quyết định kiến trúc đầy đủ: [`docs/CMS-MODULE-BOUNDARY.md`](../../../../../docs/CMS-MODULE-BOUNDARY.md).

---

## Nhóm theo nghiệp vụ

Soi chiếu cách `app/Services` đã chia (Cart, Checkout, Product, Voucher…) —
cùng một trục, để đọc code đi từ controller xuống service không phải đổi hệ
quy chiếu giữa chừng.

| Thư mục | Chứa gì |
|---|---|
| `Catalog/` | Sản phẩm, biến thể, danh mục, thuộc tính, tag, nhà sản xuất, tồn kho, kho |
| `Order/` | Đơn hàng, trạng thái, thanh toán, vận chuyển, hoàn/huỷ |
| `Customer/` | Khách hàng, nhóm khách, địa chỉ, affiliate, review |
| `Marketing/` | Coupon, voucher, quà tặng, khuyến mãi, banner, reward |
| `Content/` | Blog, trang tĩnh, menu, SEO, media |
| `System/` | Setting, user quản trị, role/permission, ngôn ngữ, log, audit |

Chưa chắc chắn thì đặt tạm ở gốc rồi dời khi nhóm đã rõ hình — dời file trong
cùng repo là thao tác rẻ. **Đừng** đẻ nhóm thứ 7 chỉ vì một controller khó
xếp.

Controller cũ (`AuthController`, `CategoryController`, `ProductController`,
`ResourceController`, `SystemController`) tạm để ở gốc, dời dần khi có dịp
chạm vào. Namespace đổi thì nhớ sửa `use` trong `routes/rcms.php` — file đó
dùng FQCN dạng mảng nên không có magic namespace nào tự lo giúp.

## Vì sao CMS không có cây `app/Cms/` riêng

Repo đi theo **layer trước, area sau**: `Http/Controllers/{Web,Cms,Api}`,
`Http/Requests/{Web,Cms}`, `Data/Cms`. Quy ước đó không tuỳ tiện — nó mã hoá
đúng kiến trúc đang theo:

- Tầng HTTP chia theo **area**, vì đây đúng là chỗ CMS và storefront khác nhau.
- Tầng domain (`app/Services`, `app/Repositories`, `app/Models`) chia theo
  **nghiệp vụ**, vì nó dùng chung — không có "phía CMS" hay "phía web".

Tức: *ranh giới area dừng ở tầng HTTP, xuống dưới là tài sản chung.* Một cây
`app/Cms/Services/` sẽ mời gọi bỏ logic nghiệp vụ vào silo riêng của CMS —
đúng thứ cần tránh khi đã quyết định giữ một nguồn sự thật cho tồn kho, giá,
coupon.

## Luật phụ thuộc (CI ép)

```
        Cms  ──────►  Domain (Models, Repositories, Services, Enums, Jobs…)
                         ▲
        Web ─────────────┘

        Web    ─╳─►  Cms      ← CẤM
        Domain ─╳─►  Cms      ← CẤM
```

`deptrac.yaml` gom layer bằng **pattern đường dẫn**, nên luật có hiệu lực dù
file nằm ở `Api/Cms/` hay nhóm con nào. Chạy:

```bash
composer boundary
```

Luật `Web ─╳─► Cms` quan trọng nhất: nó giữ cho CMS là một **lá** trong đồ thị
phụ thuộc — thay đổi CMS không có đường làm sập trang bán hàng, và nếu một
ngày thật sự cần rút CMS ra repo riêng thì lá cắt ở đâu cũng được.

## Service chỉ CMS dùng thì đặt đâu

Mặc định: **không tạo chỗ mới**. `ProductWriteService` trong
`app/Services/Product` vốn đã là service CMS dùng — cứ mở rộng nó.

Chỉ khi có orchestration thật sự không dính storefront (export Excel, import
hàng loạt, báo cáo quản trị) mới mở `app/Services/Cms/`. Ngưỡng để tạo thư mục
đó: **logic không chạm quy tắc nghiệp vụ nào** (không tính tiền, không trừ
kho, không xét điều kiện coupon). Chạm là dấu hiệu nó thuộc về domain chung.
