# Hệ theme storefront — nhiều shop, một codebase

> Mục đích: chạy nhiều bản demo giao diện gửi khách xem mẫu, **không** fork
> repo và **không** mỗi shop một nhánh git.

---

## 1. Vì sao không dùng nhánh git cho mỗi shop

Backend thay đổi liên tục. Mỗi bản vá (chống oversell, bảo mật, hiệu năng)
phải merge vào **từng** nhánh demo — 5 shop là 5 lần merge, cho mỗi lần sửa,
mãi mãi. Demo lại không phải thứ dùng xong xoá: khách xem rồi hai tuần sau
quay lại, nhánh phải sống tiếp.

Xung đột merge sẽ dồn đúng vào chỗ đụng nhiều nhất. Theme sửa
`product/structure/_product.blade.php`, `economer` cũng sửa file đó → mỗi lần
merge là một lần gỡ tay.

Và về vận hành: 5 nhánh nghĩa là 5 lần checkout, 5 container. Không gửi được
khách 5 đường link từ một máy chủ.

**Khi nào nhánh riêng mới đúng:** lúc khách đã ký và dự án đi đường riêng —
không còn là demo. Lúc đó fork là chuyện đương nhiên.

---

## 2. Cơ chế — mô hình kiểu WordPress

Namespace `web` được gắn lại **theo từng trang**, dựa trên việc theme có sở
hữu view đó hay không (`ThemeManager::applyForView()`, gọi từ
`Controller::render()`):

| | Namespace `web` | Kết quả |
|---|---|---|
| Theme **có** view | `[themes/<theme>/views, web/views]` | cả trang chạy bằng theme: layout, header, footer, partial |
| Theme **không có** | `[web/views]` | trang chạy y như khi không bật theme nào |

**Bỏ hẳn đường dẫn theme, không để rơi từng file.** Nếu giữ, một trang của
base vẫn nhặt được layout/header/footer của theme — tức trộn chrome mới với
CSS cũ. Đó là nguồn của cả loạt lỗi phải vá ngoài `@layer`
(`section{display:block}` nuốt `.grid`, `img{height:auto}` nuốt `h-10`).
Không trộn thì không có gì để vá.

Đi kèm: `<head>` chỉ nạp `main.css`/`custom.css` cho trang của base, và
`viteEntry()` trả CSS của base cho trang của base.

**Đánh đổi:** khách bấm từ trang chủ (theme) sang giỏ hàng (base) sẽ thấy
header đổi kiểu. Hết cấn khi theme phủ dần các trang chính.

Hệ quả quan trọng nhất vẫn giữ: **một theme chỉ chứa file nó ghi đè.** Base có
**113 file blade**; `aurora` hiện có **5**.

| Thành phần | Vai trò |
|---|---|
| `config/theme.php` | theme mặc định, bản đồ host → theme, allowlist |
| `app/Helpers/ThemeManager.php` | suy theme từ request, trỏ namespace, chọn entry Vite |
| `app/Http/Middleware/ResolveTheme.php` | gọi `ThemeManager::apply()` mỗi request |
| `bootstrap/app.php` | gắn middleware vào nhóm `web`, **trước** `cache_page` |
| `vite.config.js` | quét `resources/themes/*/css/app.css` thành entry riêng |
| `resources/web/views/share/head.blade.php` | `@vite([ThemeManager::viteEntry()])` |
| `resources/web/views/share/_theme_popup.blade.php` | popup chọn theme, chỉ include ở trang chủ |
| `resources/web/views/share/_theme_switcher.blade.php` | mục "Giao diện" hover ở header base (aurora có bản inline riêng trong `share/menu.blade.php` vì khác bộ token Tailwind) |

Cả popup lẫn switcher tự ẩn khi `allow_query_override` tắt hoặc không có
theme nào ngoài base (`ThemeManager::shouldShowSwitcher()`) — cùng cờ an toàn
production nói ở mục 4.

---

## 3. Thêm một theme mới

```bash
mkdir -p resources/themes/nova/{views,css}
```

**a. Entry CSS** — `resources/themes/nova/css/app.css`:

```css
@import '../../../web/css/app.css';   /* PHẢI import, đừng copy — xem §5 */
@source '../views/**/*.blade.php';

@theme {
    --color-brand: #1B4DFF;
    --font-sans: 'Inter', ui-sans-serif, system-ui, sans-serif;
}
```

**b. Khai báo** trong `config/theme.php`:

```php
'available' => ['aurora', 'nova'],
'hosts' => [
    'nova.demo.infun.co' => 'nova',
],
```

**c. Ghi đè view nào cần** — chỉ tạo đúng file đó dưới
`resources/themes/nova/views/` theo đúng đường dẫn tương đối như base.

Đổi màu + font thôi thì **không cần file blade nào**: chỉ entry CSS là đủ.

**d. (Tuỳ chọn) Nhãn hiển thị cho popup/dropdown chọn theme** — khai thêm
trong `config/theme.php` để popup trang chủ và mục "Giao diện" ở header hiện
đúng tên/mô tả/màu thay vì rơi về mặc định (`ucfirst($slug)`, swatch xám):

```php
'labels' => [
    'nova' => 'Nova',
],
'descriptions' => [
    'nova' => 'Mô tả ngắn cho theme này.',
],
'swatches' => [
    'nova' => '#1B4DFF',
],
```

Danh sách theme cho UI này đến từ `ThemeManager::options()` — tự động thêm
theme mới khi nó có mặt trong `available`, không phải sửa view popup/header.

---

## 4. Chạy demo

**Một deployment, nhiều hostname** — cách nên dùng khi gửi khách:

```php
// config/theme.php
'hosts' => [
    'aurora.demo.infun.co' => 'aurora',
    'nova.demo.infun.co'   => 'nova',
],
```

Trỏ cả hai bản ghi DNS về cùng LB, thêm `server_name` vào nginx. Gửi khách
nhiều link, sửa một chỗ là cả đàn cùng cập nhật.

**Ép theme bằng query** — tiện khi chưa có DNS:

```env
THEME_ALLOW_QUERY=true      # CHỈ bật ở staging
```

rồi `?theme=nova`. **Không bật ở production:** `CachePage` lấy query làm khoá
cache, bật lên là mở đường cho crawler sinh vô hạn key.

**Cố định một theme cho cả site:**

```env
THEME=aurora
```

Bỏ trống `THEME` và để `hosts` rỗng → chạy đúng như trước khi có hệ theme.

---

## 5. Sáu chỗ dễ vỡ

**Octane khoá cứng theme của request đầu tiên.** Dự án dùng laravel/octane +
RoadRunner: app boot một lần rồi phục vụ hàng nghìn request. Đăng ký namespace
trong `AppServiceProvider::boot()` là aurora.demo và nova.demo sẽ ra cùng một
giao diện, tuỳ ai gọi trước. Đây là lý do có middleware `ResolveTheme` — provider
chỉ đặt base làm mặc định. Bug này **không xuất hiện trên php-fpm** (mỗi request
boot lại) nên rất dễ lọt qua dev rồi vỡ ở staging.

**ViewFinder nhớ đường dẫn đã resolve.** Đổi namespace mà không
`View::getFinder()->flush()` thì finder vẫn trả file của theme trước.
`ThemeManager::apply()` đã lo, nhưng đừng gọi `View::addNamespace('web', …)` ở
chỗ khác mà quên flush.

**Khoá cache trang phải có theme.** `CachePage::cacheKey()` trước đây là
`path | locale | currency`. Nhiều theme chung một Redis, cùng path/locale/currency
→ **trùng khoá**, HTML của aurora được phục vụ cho nova. Đã thêm `theme` vào
khoá. Bug chỉ lộ khi có ≥2 theme, tức đúng lúc demo cho khách.

**Entry CSS theme phải `@import` base, không copy.** Tailwind v4 dò class từ
`@source`; base app.css đã trỏ vào `resources/web/views/**`. Copy nội dung thì
phải nhớ đồng bộ tay mãi mãi.

**CSS thuần đặt trong file được `@import` sẽ BỊ MẤT.** Đã kiểm: khối viết ở
`resources/web/css/app.css` không xuất hiện trong output của entry theme
(`.inline-grid` biến mất), trong khi rules viết TRỰC TIẾP ở entry theme
(`.aurora-footer-col`) thì còn. Cần CSS ngoài `@layer` thì viết thẳng vào
entry của theme.

**`?theme=` không được cache.** `CachePage` có `neverCacheParams` để chặn —
thiếu nó thì mọi sửa đổi bị bản HTML cũ che tới 24h, và người sửa sẽ đi tìm
nguyên nhân ở CSS/build/browser cache. Page cache hiện đang TẮT trong lúc dựng
theme (`PAGE_CACHE` trong `CachePage::handle`).

**Ghi đè view có `@section` thì phải khai lại đủ.** Blade không kế thừa section
giữa hai file cùng tên view. Ghi đè `page/home.blade.php` mà bỏ khối
`@section('meta')` là mất sạch thẻ OG/Twitter.

**Theme lấy từ host/query là input người dùng.** `ThemeManager::sanitize()` chỉ
chấp nhận giá trị nằm trong `config('theme.available')`. Đừng đổi sang quét thư
mục — ghép thẳng vào đường dẫn là path traversal.

---

## 6. Lệnh sau khi đổi theme

```bash
php artisan view:clear      # compiled view cache
php artisan config:clear    # nếu vừa sửa config/theme.php
npm run build               # build lại entry CSS của các theme

# Xoá cache trang (khoá đã gồm theme, nhưng dữ liệu cũ vẫn nằm đó)
php artisan tinker --execute="App\Helpers\CacheGate::flushPages();"
```

Với `opcache.validate_timestamps=0` ở staging/prod, đổi blade **phải build lại
image** — xem `docs/DOCKER.md`.
