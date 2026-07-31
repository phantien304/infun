# Tách tải CMS khỏi storefront — ranh giới module + bulkhead runtime

> Bối cảnh: mục tiêu 30k user active (xem `docs/SCALE-30K.md`). CMS hiện mới có
> 6 controller vì chưa viết; làm đủ cho 166 bảng sẽ cần ~90 controller chạm gần
> hết 151 entity trong `app/Models/Entities`.
>
> Quyết định: **KHÔNG tách repo CMS. Tách runtime, và dựng ranh giới module
> trong cùng một codebase.**

---

## 1. Vì sao không tách repo

Câu hỏi ban đầu là "tách hẳn bộ code CMS ra khỏi storefront để giảm tải?".
Tách **code** và tách **tải** là hai việc khác nhau, và chỉ việc thứ hai giải
quyết được vấn đề.

**Tách code không giảm tải.** Request admin vẫn tốn đúng ngần ấy CPU và vẫn
đánh vào cùng RDS, dù nó chạy từ repo nào. Thứ làm khách hàng chậm là admin
**chiếm slot php-fpm** của storefront — mà slot đó tách được bằng container
riêng trên cùng image.

**90 controller lại là lý do mạnh hơn để không tách.** Chúng phủ 166 bảng,
tức sẽ chạm gần hết tầng domain dùng chung. Tách repo thì chỉ có hai đường,
cả hai đều xấu:

| Đường | Hệ quả |
|---|---|
| Nhân đôi Models + Repositories sang repo mới | Hai bản schema mapping trôi dạt khỏi nhau. Nguy hiểm nhất: hai bản logic chống oversell (`ProductVariantWriter::updateOnHand`, clamp `on_hand ≥ 0`) — vá một bên, quên bên kia. |
| Đẩy domain thành package composer dùng chung | Repo CMS thành lớp vỏ HTTP mỏng trên một package vẫn phải version cùng nhịp storefront. Trả đủ giá 2 pipeline CI mà gần như không decouple được gì. |

Thêm một hỏng hóc cụ thể ít ai lường trước: `CacheFlushObserver` bắn
`CacheGate::flushPages()` khi admin sửa Product/Category. Observer đó nằm
trong app storefront. Admin ghi DB từ app khác → observer không chạy →
storefront phục vụ trang cũ tới hết TTL 24h. Sẽ phải dựng lại kênh invalidate
xuyên app (Redis pub/sub hoặc webhook), tức làm lại từ đầu một thứ đang chạy
tốt.

**Khi nào mới rút ra thật:** khi CMS có team riêng và nhịp release lệch hẳn,
hoặc cần runtime khác. Lúc đó, nếu luật ở §2 được giữ (CMS là một *lá* trong
đồ thị phụ thuộc), việc rút ra là cơ học chứ không phải viết lại. Phân tích
chi tiết ba topology và chi phí thật: §2b.

---

## 2. Ranh giới module (tầng code)

### Cấu trúc — theo quy ước sẵn có, KHÔNG dựng cây `app/Cms/`

Repo đi theo **layer trước, area sau**. Bản nháp đầu của tài liệu này từng đề
xuất một cây `app/Cms/{Controllers,Requests,Data,Services}` riêng; đã bỏ, vì
nó tạo ra **hai hệ quy chiếu song song** — mỗi lần tìm một controller CMS lại
phải đoán nó nằm nhánh nào. Với 90 controller thì đó không phải bất tiện nhỏ.

```
app/Http/Controllers/Api/Cms/    ← 90 controller CMS, chia nhóm nghiệp vụ
    Catalog/ Order/ Customer/ Marketing/ Content/ System/
app/Http/Controllers/Cms/        ← Blade CMS (/vcms)
app/Http/Requests/Cms/           ← FormRequest CMS
app/Data/Cms/                    ← DTO CMS

app/Models/       ┐
app/Repositories/ │ tầng Domain — nguồn sự thật dùng chung.
app/Services/     │ Cả CMS lẫn storefront đều gọi vào đây.
app/Jobs/ …       ┘

app/Http/Controllers/Web/        ← storefront (đường nóng, 30k active)
```

Quy ước sẵn có không tuỳ tiện — nó **mã hoá đúng kiến trúc ta vừa chốt**:

- Tầng HTTP chia theo **area** (`Controllers/Web` vs `Controllers/Cms`), vì
  đây đúng là chỗ CMS và storefront khác nhau thật.
- Tầng domain chia theo **nghiệp vụ** (`Services/Cart`, `Services/Checkout`,
  `Services/Product`), vì nó dùng chung — không có "phía CMS" hay "phía web".

Tức: *ranh giới area dừng ở tầng HTTP, xuống dưới là tài sản chung.* Một cây
`app/Cms/Services/` sẽ mời gọi bỏ logic nghiệp vụ vào silo riêng của CMS —
đúng thứ cần tránh khi đã quyết định giữ một nguồn sự thật cho tồn kho, giá,
coupon.

Chi tiết cách nhóm 90 controller: [`app/Http/Controllers/Api/Cms/README.md`](../app/Http/Controllers/Api/Cms/README.md).

**Service chỉ CMS dùng:** mặc định không tạo chỗ mới — `ProductWriteService`
vốn đã là service CMS dùng, cứ mở rộng nó. Chỉ orchestration thật sự không
dính storefront (export, import, báo cáo) mới mở `app/Services/Cms/`; ngưỡng
là *không chạm quy tắc nghiệp vụ nào*. `deptrac.yaml` đã khai báo trước đường
dẫn đó thuộc layer Cms để nó không lẫn vào Domain.

### Luật

```
        App\Cms  ──────►  Domain
                             ▲
        Web ─────────────────┘

        Web    ─╳─►  App\Cms      ← CẤM
        Domain ─╳─►  App\Cms      ← CẤM
```

Luật quan trọng nhất là `Web ─╳─► Cms`: nó giữ cho mọi thay đổi CMS **không có
đường** làm sập trang bán hàng.

Ép bằng [deptrac](https://github.com/qossmic/deptrac), config ở `deptrac.yaml`,
chạy trên mọi PR qua `.github/workflows/quality.yml`:

```bash
composer boundary          # deptrac analyse
composer boundary:graph    # đồ thị phụ thuộc
```

deptrac gom layer bằng **pattern đường dẫn**, không phải bằng một cây thư mục
module. Nhờ vậy luật có hiệu lực **ngay trên code hiện có**, không cần PR dời
file nào — và thêm nhóm nghiệp vụ mới dưới `Api/Cms/` cũng không phải sửa
config.

Tại thời điểm dựng, repo có **0 vi phạm** — không cần baseline.

Hai chỗ trong config đáng biết vì chúng không hiển nhiên:

- **`app/View` cố ý không thuộc layer nào.** `App\View\FragmentCache` bị chính
  Repository gọi để invalidate (`CategoryRepository`, `FilterRepository`,
  `ManufacturerRepository`). Xếp nó vào Web thì sinh 3 vi phạm Domain→Web ngay
  ngày đầu — mà nó là hạ tầng cache dùng chung, không phải code trình bày.
- **`app/Services/Cms` bị loại khỏi collector của Domain** bằng `type: bool`.
  deptrac không dừng ở layer khớp đầu tiên; thiếu bước loại trừ thì một class
  ở đó khớp cả Cms lẫn Domain, và Web import nó vẫn pass.

### Quy tắc phân biệt nhanh

> *Storefront có bao giờ cần đoạn code này không?*
> Có → `app/Services` / `app/Repositories`.  Không → nhánh `Cms`.

Model Eloquent, logic ghi nghiệp vụ (tồn kho, đơn, voucher) và migration
**luôn** ở tầng chung. Một schema, một chủ sở hữu.

---

## 2b. Nếu một ngày thật sự rút CMS ra repo riêng

Ghi lại để lần sau không phải phân tích lại từ đầu.

**Rút được:** 90 controller, FormRequest, DTO, routes, cấu hình Sanctum +
spatie permission — chừng **10-15%** khối lượng code CMS cần.

**Không rút được:** 151 entity, `app/Repositories`, `app/Services`, 66
migration, observers, enums. 85% còn lại phải có mặt ở cả hai bên.

Nên câu hỏi thật không phải "tách hay không" mà **"hai app dùng chung tầng
domain bằng cách nào"**. Ba đường, không hơn:

| Topology | Đánh giá |
|---|---|
| **Package composer dùng chung** (`infun/domain`) | Mỗi lần sửa service = bump version → update 2 app → deploy 2 app. Ba repo và một điệu nhảy release, cho hai app vẫn chung một database. Distributed monolith: chi phí của microservice, ràng buộc của monolith. |
| **CMS gọi API nội bộ của storefront** | Decoupling thật. Giá: xây và nuôi ~90 endpoint nội bộ *cộng* 90 controller CMS — nhân đôi bề mặt; mỗi trang list admin thêm một chặng mạng. Chỉ đáng khi API còn phục vụ thứ khác ngoài SPA của chính mình. |
| **Hai app, chung DB, nhân đôi model** | Hai bản Eloquent mapping của 166 bảng trôi dạt khỏi nhau. Không bàn. |

**Bốn thứ trong repo này làm việc tách đắt hơn mặc định:**

- `HasSchemaCache` — model query `DESCRIBE` để tự suy `$fillable` (lý do
  entrypoint phải migrate trước `config:cache`). Ràng buộc vào schema là tuyệt
  đối, không có chỗ nào để cắt.
- `CacheFlushObserver` → `CacheGate::flushPages()` — admin ghi từ app khác thì
  observer không chạy, storefront phục vụ trang cũ tới hết TTL 24h. Phải dựng
  lại kênh invalidate xuyên app.
- `FlashGateService` (Lua gate) + stock hold — chung keyspace Redis; hai app
  ghi nghĩa là hai bản implement phải khớp nhau từng ký tự key.
- Sanctum token + bảng spatie permission — chung.

**Tách repo mua thêm được gì mà bulkhead runtime chưa cho:**

| Lợi ích | Đã có từ tách runtime? |
|---|---|
| Admin không chiếm worker của khách | ✅ pool `fpm_admin` |
| Deploy CMS không reload FPM storefront | ✅ `up -d --no-deps infun-admin-php` |
| CMS crash không kéo storefront | ✅ khác process, khác container |
| Query CMS nặng không đè master | ✅ khi điền `DB_ADMIN_READ_HOST` |
| CI riêng, team tự chủ nhịp release | ❌ chỉ tách repo mới có |

Chỉ dòng cuối là thứ tách repo thật sự thêm vào — lợi ích **tổ chức**, không
phải kỹ thuật, và chỉ có giá trị khi có team riêng.

**Vì sao layout thư mục không phải yếu tố quyết định.** Trong một dự án tách
kéo dài nhiều tuần, phần di chuyển file là 1 lệnh `git mv` (nếu area-first) so
với 4 lệnh (layer-first) — chênh vài giờ. 90% công sức nằm ở cơ chế chia sẻ
domain, invalidate xuyên app, tách CI.

Thứ thật sự quyết định tách rẻ hay đắt là **luật deptrac**: nếu
`Web ─╳─► Cms` và `Domain ─╳─► Cms` được giữ, CMS là một **lá** trong đồ thị
phụ thuộc — lá thì cắt ở đâu cũng được, nằm thư mục nào cũng vậy. Nếu để
storefront lỡ import vào CMS vài chục lần thì area-first cũng không cứu nổi.

---

## 3. Bulkhead runtime (tầng hạ tầng)

Đây là phần thật sự giảm tải.

```
                      ┌── /vcms, /rcms ──►  fpm_admin  ──►  infun-admin-php
   nginx (infun-web) ─┤                                     pool: 6 worker
                      └── còn lại ───────►  fpm_pool   ──►  infun-php ×2
                                                            pool: 60 worker
```

### Vì sao pool riêng, không chỉ là "thêm máy"

Storefront và CMS có profile tải **ngược nhau**:

| | storefront | CMS |
|---|---|---|
| Lưu lượng | hàng nghìn req ngắn | vài chục req/phút |
| Kỳ vọng | p95 < 200ms | vài phút cũng chấp nhận |
| `request_terminate_timeout` | **15s** — kẹt lâu hơn là dồn hàng đợi theo cấp số nhân (đo được ở k6, xem `SCALE-30K.md`) | **300s** — export/import chạy lâu là bình thường |

Chung một pool thì phải chọn một con số sai cho một trong hai bên. Tách pool
cho cả hai đúng, **đồng thời** dựng bulkhead: admin bận hết 6 worker của mình
thì admin chậm, khách mua hàng không biết gì.

### Các file đã đổi

| File | Thay đổi |
|---|---|
| `docker/php/www.admin.conf` | Pool admin: `pm.static`, 6 children, terminate 300s, memory 768M |
| `docker/php/Dockerfile.staging` | Bake pool trên vào `/usr/local/etc/php-fpm.d/available/` (không tự nạp) |
| `docker/php/entrypoint.staging.sh` | `FPM_POOL_PROFILE=admin` → copy pool đè `zz-www.conf` trước khi exec |
| `docker/nginx/default.lb.conf` | `upstream fpm_admin` + location `^/(vcms\|rcms)` + vhost `cms.infun.co` |
| `docker-compose.production.yml` | Service `infun-admin-php`, `infun-queue-admin` (replicas 0) |
| `.env.production.example` | `PROD_ADMIN_PHP_REPLICAS`, `DB_ADMIN_READ_HOST`, `PROD_QUEUE_ADMIN_REPLICAS` |

Một image, một codebase — chỉ khác env. Không phá nguyên tắc *"tested =
shipped"* (prod promote đúng image staging đã test, xem `CI-CD-PRODUCTION.md`).

### Cạm bẫy nginx đã né

Location `^/(vcms|rcms)` **không** dùng `try_files ... /index.php?$query_string`.
try_files sinh internal redirect tới `/index.php`, và `/index.php` lại khớp
`location ~ \.php$` bên dưới → request quay về `fpm_pool` của storefront, phá
sạch bulkhead **mà không có dấu hiệu gì** (vẫn trả 200, chỉ là chạy nhầm pool).
Nên location đó gọi thẳng `fastcgi_pass` và tự set `SCRIPT_FILENAME`.

### Deploy riêng dù chung image

```bash
docker compose -f docker-compose.production.yml up -d --no-deps infun-admin-php
```

Sửa CMS không cần reload FPM storefront. Với `opcache.validate_timestamps=0`,
mỗi lần reload storefront là một lần chạm vào đường nóng — đây là phần lớn lợi
ích "deploy riêng" mà nhiều người tưởng phải tách repo mới có.

### Read replica

`infun-admin-php` set **cả hai** biến:

```yaml
DB_READ_HOST1: "${DB_ADMIN_READ_HOST:-${DB_HOST}}"
DB_WRITE_HOST: "${DB_HOST}"
```

Phải set cả hai. `config/database.php` fallback read *và* write về `DB_HOST`,
nên chỉ đổi read mà quên write thì lệnh ghi của CMS bay vào replica
(`read_only`) → lỗi ngay lúc lưu sản phẩm. Chưa dựng replica thì để
`DB_ADMIN_READ_HOST` trống, cả hai tự fallback như cũ.

---

## 4. Ngân sách RAM — đọc trước khi bật

Trên **t3.small (2GB)** hiện chạy `PROD_PHP_REPLICAS=2`. Pool admin 6 worker
× ~90MB RSS ≈ **540MB** lấy khỏi storefront. Vẫn chạy được, nhưng biên an toàn
mỏng.

- Muốn cả hai thoải mái: resize **t3.medium (4GB)**.
- Hoặc tạm hạ `pm.max_children` của pool admin xuống 3 trong
  `docker/php/www.admin.conf`.
- Hoặc `PROD_ADMIN_PHP_REPLICAS=0` để tắt hẳn (đường dẫn admin sẽ 502,
  storefront không ảnh hưởng) cho tới khi resize.

`infun-queue-admin` để `replicas=0` vì hiện **chưa job nào** gọi
`->onQueue('admin')` — bật lên chỉ tốn ~90MB để idle.

---

## 5. Việc còn lại

- [ ] Job CMS mới dispatch bằng `->onQueue('admin')`, rồi đặt
      `PROD_QUEUE_ADMIN_REPLICAS=1`. Chung queue thì một lần
      `images:migrate-r2` là khách chờ mail xác nhận đơn hàng chục phút.
- [ ] Dựng RDS Read Replica → điền `DB_ADMIN_READ_HOST`. **Đây mới là phần
      giảm tải lớn nhất** — tách container chỉ cách ly CPU/worker của PHP,
      còn RDS master vẫn dùng chung. Một query CMS quét bảng lớn vẫn kéo cả
      sàn xuống nếu chưa tách đường đọc.
- [ ] Trỏ DNS `cms.infun.co` về LB production để dùng vhost riêng (hiện
      hostname này mới chỉ tồn tại local qua file hosts + Herd, xem
      `infuncms/deploy/SETUP-cms.infun.co.md`). Định tuyến path-based đã hoạt
      động sẵn nên đây chỉ là lựa chọn thêm.
- [ ] Đặt `/vcms`, `/rcms` sau VPN hoặc IP allowlist (`LIMIT_ACCESS_*` hoặc
      security group). Tách endpoint xong thì tách luôn bề mặt tấn công.
- [ ] Áp cùng cấu hình cho `docker-compose.staging.yml` để staging phản chiếu
      prod (patch này mới chỉ đụng production).
