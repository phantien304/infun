# Chạy infun + infun_cms bằng Docker Desktop

Stack dev cho 2 dự án, thay thế hoàn toàn XAMPP. Tất cả file Docker đã đặt
trong `infun/`; `infun_cms/` chỉ có `Dockerfile` riêng và được build từ
compose của `infun`.

## Layout file

```
infun/
  docker/
    php/Dockerfile        # PHP 8.4-FPM + ext (pdo_mysql, redis, gd, ...)
    php/entrypoint.sh     # composer install + fix quyền storage
    nginx/default.conf    # vhost Laravel → FPM
  docker-compose.yml      # toàn bộ services
  .dockerignore

infun_cms/
  Dockerfile              # Node 20 + Vite dev server
  .dockerignore
```

## Yêu cầu

- Docker Desktop bật **WSL2 backend** (Windows).
- TẮT MySQL/Apache trong XAMPP Control Panel trước khi up (cổng 3306/80
  sẽ kẹt nếu để chạy).
- 2 thư mục `infun/` và `infun_cms/` nằm cùng cấp dưới `E:\xampp82\htdocs\`
  (compose dùng đường dẫn tương đối `../infun_cms`).

## Chạy lần đầu

```bash
cd E:\xampp82\htdocs\infun
docker compose up -d --build
```

Lần đầu sẽ mất 3-5 phút để pull image + build PHP + `composer install` +
`npm install` cho cả 2 project. Theo dõi tiến trình:

```bash
docker compose logs -f infun-php infun-vite infun-cms
```

Khi thấy `php-fpm ready`, `VITE ready`, `Local: http://localhost:5173` →
xong.

## Setup .env

### `infun/.env`

Sửa lại để trỏ vào service Docker (KHÔNG dùng 127.0.0.1):

```ini
APP_URL=http://localhost:8000

DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=infun
DB_USERNAME=root
DB_PASSWORD=root

REDIS_HOST=redis
REDIS_PORT=6379
```

Compose đã override sẵn các biến DB/Redis qua `environment:` nên kể cả
.env quên sửa vẫn chạy được — nhưng artisan command (chạy ngoài container)
sẽ đọc .env, nên sửa luôn cho gọn.

### `infun_cms/.env`

```ini
VITE_API_BASE_URL=http://localhost:8000/rcms
VITE_LARAVEL_ORIGIN=http://localhost:8000
VITE_AREA=CMS
```

Sau khi đổi .env của CMS, restart container để Vite reload biến:

```bash
docker compose restart infun-cms
```

## Truy cập

| Service              | URL / Host                  |
|---                   |---                          |
| Laravel infun        | http://localhost:8000       |
| Vite (Laravel @vite) | http://localhost:5174       |
| infun_cms (SPA)      | http://localhost:5173       |
| MariaDB              | 127.0.0.1:3306 (root/root)  |
| Redis                | 127.0.0.1:6379              |
| Redis Insight (GUI)  | http://localhost:5540       |

## Lệnh hay dùng

```bash
# Vào shell PHP để chạy artisan
docker compose exec infun-php sh
php artisan migrate
php artisan tinker

# Hoặc chạy 1 lệnh artisan trực tiếp
docker compose exec infun-php php artisan migrate:fresh --seed

# Vào MariaDB
docker compose exec mysql mariadb -uroot -proot infun

# Vào Redis CLI
docker compose exec redis redis-cli

# Stop
docker compose down

# Stop + xoá DB
docker compose down -v
```

## Import DB cũ từ XAMPP

```bash
# 1. Dump từ XAMPP (chạy ngoài Docker, dùng mysqldump của XAMPP):
E:\xampp82\mysql\bin\mysqldump.exe -uroot infun > infun.sql

# 2. Import vào container:
docker compose exec -T mysql mariadb -uroot -proot infun < infun.sql
```

## Troubleshooting

**"port 3306 already in use"** → MySQL của XAMPP còn chạy. Tắt trong
XAMPP Control Panel hoặc đổi mapping `"3307:3306"` trong compose.

**"port 80/8000 in use"** → Apache XAMPP. Tắt Apache hoặc đổi `"8001:80"`.

**Vite HMR không reload** → Windows file system event không bắn vào
container. Compose đã set `CHOKIDAR_USEPOLLING=true` cho `infun-cms`, nếu
vẫn lỗi, thêm `WATCHPACK_POLLING=true` cho `infun-vite`.

**Lỗi `permission denied` ở storage/** → entrypoint đã `chmod` lúc start.
Nếu vẫn lỗi:
```bash
docker compose exec infun-php chown -R www-data:www-data storage bootstrap/cache
```

**`vendor/` rỗng / `class not found`** → entrypoint tự chạy
`composer install` lần đầu. Nếu muốn chạy tay:
```bash
docker compose exec infun-php composer install
```

**`npm install` chậm hoặc lỗi native module** → xoá volume node_modules
rồi build lại:
```bash
docker compose down
docker volume rm infun-stack_infun_node_modules infun-stack_cms_node_modules
docker compose up -d --build
```

## Laravel Scout + Meilisearch (search 500k product)

Đã thêm vào stack:
- `laravel/scout` ^10.18 + `meilisearch/meilisearch-php` ^1.15 trong
  `composer.json`.
- Service `meilisearch` trong `docker-compose.yml` (port 7700, volume
  riêng `meilisearch_data`).
- Trait `Searchable` trong `app/Models/Entities/Product.php` +
  `toSearchableArray()`.
- Seeder `database/seeders/BulkProductSeeder.php` (500k product chunked).

### Cài + setup

```bash
# 1. Cài composer package
docker compose exec infun-php composer install

# 2. (Lần đầu) up Meilisearch container
docker compose up -d meilisearch

# 3. Publish config Scout — sửa được prefix, soft_delete, ...
docker compose exec infun-php php artisan vendor:publish \
    --provider="Laravel\Scout\ScoutServiceProvider"
```

Thêm vào `.env` của infun:
```ini
SCOUT_DRIVER=meilisearch
SCOUT_QUEUE=true          # đẩy index task vào queue, không block request
MEILISEARCH_HOST=http://meilisearch:7700
MEILISEARCH_KEY=
```

Compose đã override sẵn 3 biến đầu nên kể cả .env quên vẫn chạy. `SCOUT_QUEUE=true`
yêu cầu queue worker chạy (sẽ note bên dưới).

### Seed 1.5-2M review

Command `reviews:seed` đã có sẵn — đã raw `DB::insert()` chunked + bypass
observer, đủ scale tới 2M. Mỗi review sinh kèm:

- 1 `review` row
- ~5 `review_rating` row (multi-criteria: quality + service + packaging + …)
- 0-3 `review_media` row (~25% review có ảnh)
- 1-3 `review_tag_pivot` row (~60% review có tag)
- 1-15 `review_helpful` row (~50% review có vote)

→ Seed 2M review = **~25-30M row insert tổng cộng**.

**Lệnh đầy đủ (truncate sạch rồi seed):**

```bash
# Cần seed product trước (review FK tới product.id)
docker compose exec infun-php php artisan reviews:seed 2000000 \
    --truncate --chunk=1000
```

**Time estimate trên SSD + MariaDB 10.11 (Docker local):**

| Số review | Tổng row | Thời gian |
|---:|---:|---:|
| 100k | ~1.5M | 1-2 phút |
| 500k | ~7M  | 5-10 phút |
| 1M | ~15M | 15-25 phút |
| **2M** | **~30M** | **40-70 phút** |

**Recipe nhanh hơn (bỏ media/helpful — phù hợp test list/sort/aggregate, không test UI media):**

```bash
docker compose exec infun-php php artisan reviews:seed 2000000 \
    --truncate --chunk=1000 --no-media --no-helpful
# → giảm còn ~12M row, chạy ~15-25 phút
```

**Recipe progressive (vừa làm vừa quan sát):**

```bash
docker compose exec infun-php php artisan reviews:seed 500000 --truncate --chunk=1000
# Theo dõi DB size + time. Nếu OK thì seed tiếp 1.5M (KHÔNG --truncate):
docker compose exec infun-php php artisan reviews:seed 1500000 --chunk=1000
# → tổng 2M không phải đợi seed lại từ đầu nếu thấy gì sai sớm
```

**Tune memory** — `--chunk` nhỏ = chậm hơn nhưng peak memory thấp hơn:

| Chunk | Per-batch row peak | RAM peak (PHP) |
|---:|---:|---|
| 500 (default) | ~7.5k | ~200 MB |
| 1000 | ~15k | ~350 MB |
| 2000 | ~30k | ~700 MB |

Mặc định `PHP memory_limit=512M` trong Dockerfile, an toàn cho chunk=1000.
Tăng lên 1024M nếu muốn chunk=2000.

**OOM ở dataset lớn → dùng `reviews:seed-bulk` (split-process):**

Ở 1M+ review, PHP có memory leak ngầm (framework static state + Carbon +
PDO buffer) không API nào free được trong cùng process — kể cả set 4GB
vẫn die ở ~50%. Cách CHẮC NHẤT: chia thành nhiều process, mỗi process exit
sau khi xong → OS free memory tận gốc.

Đã thêm wrapper `reviews:seed-bulk`:

```bash
# 2M review chia 10 process × 200k. Mỗi process die <1.5GB.
docker compose exec infun-php php artisan reviews:seed-bulk 2000000 \
    --truncate --chunk=300

# Per-run nhỏ hơn nếu vẫn OOM
docker compose exec infun-php php artisan reviews:seed-bulk 2000000 \
    --truncate --chunk=200 --per-run=100000

# Bỏ helpful để nhanh + nhẹ hơn nhiều
docker compose exec infun-php php artisan reviews:seed-bulk 2000000 \
    --truncate --chunk=300 --no-helpful
```

Cơ chế:
- Process 1: `--truncate --no-aggregate` (xoá rồi seed 200k)
- Process 2-10: `--no-aggregate` (seed thêm 200k)
- Sau cùng: tự gọi `reviews:rebuild-aggregate` UPDATE 1 lần (GROUP BY)

Nếu muốn chạy thủ công split-run:

```bash
docker compose exec infun-php php artisan reviews:seed 200000 --truncate --no-aggregate --chunk=300
docker compose exec infun-php php artisan reviews:seed 200000 --no-aggregate --chunk=300
# ... lặp đến đủ ...
docker compose exec infun-php php artisan reviews:rebuild-aggregate
```

**OOM (`Allowed memory size exhausted`) — quick fix cho single-process:**

PDO prepared statement buffer + bulk INSERT array có thể chạm 800MB ngay từ
5-10% tiến độ ở dataset 2M, kể cả `unset()` + `gc_collect_cycles()` không
giải được. Đã fix bằng 2 cách:

1. **Bump default memory** — Dockerfile `zz-app.ini` đã set `memory_limit=1024M`
   (trước là 512M). Rebuild image để áp:
   ```bash
   docker compose build infun-php && docker compose up -d infun-php
   ```

2. **Periodic `DB::disconnect()`** — `SeedReviewsCommand` mỗi 50 batch tự reset
   connection → RAM về baseline. Đã commit, chỉ cần chạy lại.

**Workaround không rebuild image** (nếu chưa rebuild kịp):

```bash
docker compose exec infun-php php -d memory_limit=2G artisan reviews:seed \
    2000000 --truncate --chunk=500
```

**Vẫn OOM?** Giảm `--chunk`:

```bash
docker compose exec infun-php php -d memory_limit=2G artisan reviews:seed \
    2000000 --truncate --chunk=200 --no-helpful
```

`--no-helpful` cắt 50% row con (lớn nhất trong cluster) → memory peak halve.

**Validation sau seed:**

```bash
docker compose exec infun-php php artisan tinker
>>> DB::table('review')->count()                                        // ~2M
>>> DB::table('review_rating')->count()                                 // ~10M
>>> DB::table('review_media')->count()                                  // ~1.5M
>>> DB::table('review_helpful')->count()                                // ~15M
>>> DB::table('product')->where('review_count', '>', 0)->count()        // ~500k (≈ all products)
>>> DB::table('product')->avg('rating_avg')                             // ~4.3 (skew positive)
>>> DB::table('product')->orderBy('review_count', 'desc')->take(5)->get(['id','review_count','rating_avg'])
```

**ANALYZE sau seed** (planner cần stats mới, không tự cập nhật ngay):

```bash
docker compose exec mysql mariadb -uroot -proot infun -e \
    "ANALYZE TABLE review, review_rating, review_media, review_helpful, review_tag_pivot, product"
```

**Test perf list + filter rating với k6** (kết hợp setup load test trước):

```bash
# Bắn 50 VUs vào endpoint list filter "5 sao" để xem index rating_avg có hoạt động
docker compose run --rm -e BASE_URL=http://infun-web \
    -e VUS=50 -e DURATION=60s \
    k6 run /scripts/load-test.js
```

### Purge product cũ (truncate sạch không seed lại)

```bash
# Confirm prompt trước khi xoá
docker compose exec infun-php php artisan products:purge

# Skip confirm (cho script CI)
docker compose exec infun-php php artisan products:purge --force

# Giữ taxonomy (category, manufacturer, filter) — chỉ xoá product
docker compose exec infun-php php artisan products:purge --keep-taxonomy

# Giữ luôn option seed (Color, Size đã setup) — re-seed variant nhanh hơn
docker compose exec infun-php php artisan products:purge --keep-taxonomy --keep-options

# Flush luôn index Meilisearch (chạy sau purge)
docker compose exec infun-php php artisan scout:flush "App\\Models\\Entities\\Product"
```

Command in trước danh sách bảng + số row sắp xoá để bạn confirm — không
xoá nhầm.

### Seed 500k product (mix SIMPLE + VARIANT)

Project đã có sẵn 4 console command — wrapper `products:seed-all` gọi
tuần tự:

| Bước | Command | Vai trò |
|---|---|---|
| 1 | `products:seed N` | Sinh N product (taxonomy + ảnh + description + filter), KHÔNG variant |
| 2 | `variants:seed --percent=X` | Gắn variant Color×Size cho X% product (→ product VARIANT) |
| 3 | `simple-variants:seed` | Gắn 1 default variant cho phần còn lại (→ product SIMPLE, theo Shopify pattern) |
| 4 | `specials:seed --percent=Y` | Gắn campaign giảm giá lên Y% variant |

**Chạy gọn 1 lệnh:**

```bash
# Mặc định: 500k product, 40% variant, 30% specials (~5-8 phút trên SSD)
docker compose exec infun-php php artisan products:seed-all

# Tuỳ biến
docker compose exec infun-php php artisan products:seed-all \
    --total=500000 --variant-percent=40 --special-percent=30

# Reset trước khi seed
docker compose exec infun-php php artisan products:seed-all --truncate

# Test nhanh với 10k
docker compose exec infun-php php artisan products:seed-all --total=10000
```

Kết quả mẫu (500k, 40% variant):

```
Tổng product:     500000
  - variant:      ~200000 (40%)   → mỗi product 2-4 variant Color×Size
  - simple:       ~300000          → mỗi product 1 default variant
Tổng variant:     ~800000          → ~600k variant + 300k simple-default
Special campaign: ~30% variant     → ~240k variant_special active
```

**Chạy từng bước thủ công** (nếu cần kiểm soát):

```bash
docker compose exec infun-php php artisan products:seed 500000 --truncate
docker compose exec infun-php php artisan variants:seed --percent=40
docker compose exec infun-php php artisan simple-variants:seed
docker compose exec infun-php php artisan specials:seed --percent=30
```

### Architecture GIÁ — đọc trước khi sửa

Schema sau migration `drop_price_from_product` + `unify_simple_product_stock`:

```
┌────────────────────────────────┐
│ product                        │  KHÔNG còn cột `price`. Chỉ giữ
│   has_variants  (UI flag)      │  3 AGGREGATE denormalized cho list:
│   min_variant_price            │    min/max_variant_price
│   max_variant_price            │    max_variant_discount_percent
│   max_variant_discount_percent │
└────────────────────────────────┘
            │ 1..N
            ▼
┌──────────────────────┐    GIÁ THẬT nằm Ở ĐÂY (kể cả SIMPLE cũng có
│ product_variant      │    1 default variant — Shopify pattern):
│   price              │      price          = giá đang bán
│   regular_price      │      regular_price  = giá gốc (basis sale %)
└──────────────────────┘
            │ 0..N
            ▼
┌────────────────────────────┐  Override time-window + user_group.
│ product_variant_special    │  priority lớn nhất thắng.
│   price                    │  KHÔNG ghi đè variant.price; chỉ
│   date_start / date_end    │  tham gia ở runtime (effective price).
│   user_group_id, priority  │
└────────────────────────────┘
```

**Hệ quả:**
- Khi truy vấn giá hiển thị → ưu tiên `variant_special.price` đang active
  → fallback `variant.price`.
- List/filter theo giá → đọc `product.min_variant_price` /
  `product.max_variant_price` (đã có index `idx_product_variant_price`).
- KHÔNG còn `product.price` ở bất kỳ Eloquent attribute/cast/query nào.
- Sau seed, wrapper tự gọi backfill aggregate (GROUP BY + UPDATE JOIN
  trong MySQL — nhanh hơn nhiều update từng row PHP).

**Lưu ý seed**: tất cả command dùng `DB::table()->insert()` raw, KHÔNG
trigger Eloquent observer/auditing/Scout. Phải `scout:import` thủ công
sau khi xong:

### Import index vào Meilisearch

```bash
# Đẩy toàn bộ Product (đã có trait Searchable) lên index "products"
docker compose exec infun-php php artisan scout:import \
    "App\Models\Entities\Product"
```

Document đẩy lên đã có các field price aggregate (`min_variant_price`,
`max_variant_price`, `max_variant_discount_percent`) — tận dụng để
filter/sort search theo giá mà KHÔNG cần đẩy từng variant lên Meilisearch
(500k × 3 variant = 1.5M document = quá lớn cho dataset search).

Lần đầu 500k product mất ~3-5 phút (chunk 500/batch mặc định, có thể
tăng `SCOUT_CHUNK_SEARCHABLE` trong `.env` lên 2000 cho nhanh). Theo dõi:

```bash
docker compose logs -f meilisearch
```

Khi xong, mở http://localhost:7700 → tab "Indexes" → "products" thấy
500k document.

### Test search

**Tinker:**
```bash
docker compose exec infun-php php artisan tinker
>>> App\Models\Entities\Product::search('giày sneaker')->take(10)->get()
>>> App\Models\Entities\Product::search('limited')->paginate(20)
```

**HTTP** (qua Meilisearch trực tiếp, bypass Laravel để đo raw speed):
```bash
curl -X POST 'http://localhost:7700/indexes/products/search' \
    -H 'Content-Type: application/json' \
    --data-raw '{"q":"giày sneaker","limit":10}'
# → "processingTimeMs": 5-30ms cho 500k doc
```

So với `WHERE name LIKE '%giày sneaker%'` trên MySQL 500k row (~500-2000ms
+ full table scan) — Meilisearch nhanh hơn 50-200x và **xếp hạng theo độ
liên quan** (typo tolerance, phrase match).

### Tinh chỉnh ranking (optional)

Set searchable/filterable/sortable attributes — Meilisearch chỉ index
field bạn cần search trên đó, document còn lại chỉ trả về:

```bash
# Set 1 lần (qua HTTP)
curl -X PATCH 'http://localhost:7700/indexes/products/settings' \
    -H 'Content-Type: application/json' \
    --data-raw '{
        "searchableAttributes": ["name", "sku", "model", "description"],
        "filterableAttributes": ["manufacturer_id", "category_ids"],
        "sortableAttributes":   ["sort_order", "created_at"],
        "rankingRules": ["words","typo","proximity","attribute","sort","exactness"]
    }'
```

Lúc đó search có thể filter: `Product::search('áo')->where('manufacturer_id', 5)->get()`.

### Queue index sync (production)

`SCOUT_QUEUE=true` đẩy việc index vào queue Redis → CRUD product không
phải đợi Meilisearch reply. Cần queue worker:

```bash
docker compose exec infun-php php artisan queue:work redis --queue=scout
```

Hoặc thêm service `infun-queue` vào compose (mỗi lần `up` tự chạy):

```yaml
infun-queue:
  build:
    context: .
    dockerfile: docker/php/Dockerfile
  container_name: infun-queue
  command: php artisan queue:work redis --queue=scout,default --tries=3
  volumes:
    - ./:/var/www/html
  environment:
    DB_HOST: mysql
    REDIS_HOST: redis
    SCOUT_DRIVER: meilisearch
    MEILISEARCH_HOST: http://meilisearch:7700
  depends_on: [mysql, redis, meilisearch]
  networks: [infun-net]
```

### Troubleshooting

**`scout:import` báo memory limit** → Scout load Eloquent model theo
chunk; tăng `memory_limit` trong `docker/php/zz-app.ini` hoặc giảm
`SCOUT_CHUNK_SEARCHABLE` xuống 200.

**Index thiếu name** → Trait `Searchable` ở Product gọi
`description()->first()` mỗi document → chậm + N+1. Sửa
`toSearchableArray()` hoặc override `makeAllSearchableUsing(Builder $q)`
để eager load:
```php
protected function makeAllSearchableUsing(Builder $query): Builder
{
    return $query->with('description');
}
```

**Meilisearch hết RAM** → `meilisearch` default cấp không giới hạn. Thêm
`mem_limit: 1g` trong compose nếu cần chặn.

## Debugbar + Clockwork (debug perf)

Đã thêm 2 package vào `require-dev` của `composer.json`:

- **`barryvdh/laravel-debugbar`** — widget HTML cuối trang. Hợp cho route
  trả HTML (web.php). KHÔNG hiện trên JSON API.
- **`itsgoingd/clockwork`** — gửi data qua HTTP header + có app desktop +
  Chrome extension. Hợp cho **infun_cms** (React SPA gọi API JSON).

### Cài đặt

```bash
# Vào container PHP rồi install
docker compose exec infun-php composer install

# Hoặc chạy 1 phát từ ngoài
docker compose exec infun-php composer require --dev barryvdh/laravel-debugbar itsgoingd/clockwork
```

Laravel auto-discovery sẽ tự đăng ký service provider, không cần sửa
`config/app.php`.

### Dùng Debugbar (cho web routes)

Mặc định bật khi `APP_DEBUG=true` và `APP_ENV=local`. Mở bất kỳ trang
HTML nào của infun → thanh widget hiện cuối trang. Tab quan trọng:

- **Queries** — số query, thời gian từng query, file:line gọi. Phát hiện
  N+1 dễ nhất ở đây (thấy 50 query giống nhau khác mỗi id).
- **Models** — số model load + có eager load không.
- **Timeline** — boot + middleware + controller + view time.
- **Route** — controller/action + middleware chain.

Publish config nếu cần tinh chỉnh:
```bash
docker compose exec infun-php php artisan vendor:publish \
    --provider="Barryvdh\Debugbar\ServiceProvider"
```

### Dùng Clockwork (cho API + SPA)

Sau khi cài, mỗi response API đính header `X-Clockwork-Id`. Cách xem:

**Cách 1 — Chrome/Firefox extension** (khuyên dùng):
1. Cài extension [Clockwork](https://underground.works/clockwork) cho browser.
2. Mở DevTools → tab "Clockwork".
3. Refresh `infun_cms` → mỗi API call hiện 1 dòng, click vào xem queries
   / events / log / timeline giống Debugbar.

**Cách 2 — Web UI built-in:**
- Mở `http://localhost:8000/clockwork` → list request gần đây + chi tiết.

**Cách 3 — App desktop** (macOS/Linux): https://underground.works/clockwork/#download

Publish config nếu cần:
```bash
docker compose exec infun-php php artisan vendor:publish --tag=clockwork-config
```

### Workflow tìm N+1 / query chậm

1. Mở Clockwork extension, bật trên `infun_cms`.
2. Vào trang list sản phẩm → xem tab "Database":
   - **>30 queries** cho 1 trang = nghi N+1.
   - Cùng pattern `SELECT * FROM product_description WHERE product_id = ?`
     lặp 50 lần → thiếu `with('productDescription')`.
3. Sort theo "Duration" → query nào > 100ms → copy SQL chạy
   `EXPLAIN <sql>` trong MySQL container:
   ```bash
   docker compose exec mysql mariadb -uroot -proot infun -e \
     "EXPLAIN SELECT ... "
   ```
4. Nếu `type=ALL` (full scan) + `rows` lớn → tạo index.

### Lưu ý production

`require-dev` nên Debugbar/Clockwork **KHÔNG được autoload** khi deploy
prod (`composer install --no-dev`). Double-check thêm:

```php
// config/debugbar.php
'enabled' => env('DEBUGBAR_ENABLED', null), // null = chỉ chạy khi APP_DEBUG=true

// config/clockwork.php
'enable' => env('CLOCKWORK_ENABLE', null),
```

Trong `.env` prod:
```ini
APP_DEBUG=false
DEBUGBAR_ENABLED=false
CLOCKWORK_ENABLE=false
```

## Load balance + performance test

Stack thường (1 PHP-FPM, 1 nginx) không phản ánh production. Bật chế độ
LB để scale FPM thành N replicas + bắn tải bằng k6.

### Bật LB

File `docker-compose.lb.yml` là **override** — phải truyền cả 2 file:

```bash
# Lần đầu: build + scale 3 PHP backend
docker compose -f docker-compose.yml -f docker-compose.lb.yml up -d --build --scale infun-php=3

# Hoặc set 1 lần cho session (PowerShell)
$env:COMPOSE_FILE = "docker-compose.yml;docker-compose.lb.yml"
docker compose up -d --scale infun-php=5

# bash / WSL
export COMPOSE_FILE=docker-compose.yml:docker-compose.lb.yml
docker compose up -d --scale infun-php=5
```

Kiểm tra replicas:

```bash
docker compose ps
# infun-php-1, infun-php-2, infun-php-3 ... đều "Up (healthy)"

# Xem traffic phân bổ:
docker compose logs --tail=20 -f infun-web | findstr upstream=
# upstream=172.20.0.5:9000   ← replica 1
# upstream=172.20.0.7:9000   ← replica 3
# upstream=172.20.0.6:9000   ← replica 2 ...
```

Cơ chế: nginx có `resolver 127.0.0.11 valid=10s` (DNS embedded Docker) +
`upstream` với `least_conn`. Khi scale thêm replica, nginx tự pick up
trong 10s, không cần reload.

### Bắn tải bằng k6

```bash
# Test mặc định: 20 VUs, 30s
docker compose run --rm k6 run /scripts/load-test.js

# Tuỳ biến qua env
docker compose run --rm -e VUS=100 -e DURATION=60s k6 run /scripts/load-test.js

# Trỏ vào host port thay vì network nội bộ
docker compose run --rm -e BASE_URL=http://host.docker.internal:8000 \
    k6 run /scripts/load-test.js
```

Output mẫu:

```
http_req_duration..............: avg=234ms  p(95)=612ms  p(99)=1.1s
http_reqs......................: 4823     160.7/s
product_list_ms................: avg=198ms  p(95)=540ms
product_show_ms................: avg=312ms  p(95)=890ms
error_rate.....................: 0.00%
```

Sửa `docker/k6/load-test.js` (mảng `SCENARIOS`) cho khớp endpoint thật.

### Đo so sánh: 1 vs 3 vs 5 replicas

```bash
# Baseline 1 replica
docker compose -f docker-compose.yml -f docker-compose.lb.yml up -d --scale infun-php=1
docker compose run --rm -e VUS=50 -e DURATION=30s k6 run /scripts/load-test.js
# → ghi lại p95 / throughput

# 3 replicas
docker compose up -d --scale infun-php=3
docker compose run --rm -e VUS=50 -e DURATION=30s k6 run /scripts/load-test.js

# 5 replicas
docker compose up -d --scale infun-php=5
docker compose run --rm -e VUS=50 -e DURATION=30s k6 run /scripts/load-test.js
```

Nếu p95 không giảm khi scale → bottleneck KHÔNG ở PHP. Check:
- MySQL slow query (`docker compose exec mysql mariadb -uroot -proot -e "SHOW PROCESSLIST"`).
- Redis miss rate (Redis Insight → Analytics).
- nginx worker quá ít (mặc định = số CPU).

### Tune FPM worker

PHP-FPM mặc định `pm = dynamic`, `pm.max_children = 5`. Với 3 replicas chỉ
chịu được ~15 request đồng thời. Sửa nếu cần đo throughput cao:

Tạo `docker/php/fpm.conf`:
```ini
[www]
pm = dynamic
pm.max_children = 50
pm.start_servers = 10
pm.min_spare_servers = 5
pm.max_spare_servers = 20
pm.max_requests = 500
```

Thêm vào `docker/php/Dockerfile`:
```dockerfile
COPY docker/php/fpm.conf /usr/local/etc/php-fpm.d/zz-app.conf
```

Rồi `docker compose build infun-php && docker compose up -d`.

### Nâng cấp tiếp (nếu muốn)

- Thêm **HAProxy** trước nginx → có UI stats (http://localhost:8404/stats).
- Thêm **Prometheus + Grafana** để vẽ chart RPS / latency theo thời gian.
- Bật **PHP OPcache + JIT** trong `zz-app.ini` để đo tác động.

## Khác biệt so với XAMPP

- PHP đã có `pdo_mysql`, `redis`, `gd`, `intl`, `bcmath`, `zip` sẵn — không
  cần bật trong php.ini.
- Timezone đã set `Asia/Ho_Chi_Minh`.
- `upload_max_filesize` / `post_max_size` = 64M, `memory_limit` = 512M.
- Code mount từ host → sửa file thấy ngay, không cần rebuild.
