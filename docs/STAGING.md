# Staging (image bất biến) — cách build & đẩy code

Bản staging chạy **image bất biến**: code + vendor + asset build được nướng vào
image lúc `docker build`. Không mount code, không Vite hot-reload, opcache khoá
timestamp → giống production. DB / Redis / Meilisearch **riêng**, không đụng bản
dev hay Herd.

File liên quan:
- `docker/php/Dockerfile.staging` — 3 stage: `assets` (Vite) → `app` (php-fpm) → `web` (nginx).
- `docker/php/entrypoint.staging.sh` — build cache theo ENV runtime lúc container start.
- `docker-compose.staging.yml` — stack độc lập.

---

## Dev vs Staging

| | Dev (`docker-compose.yml`) | Staging (`docker-compose.staging.yml`) |
|---|---|---|
| Code | mount `./:/var/www/html` (sửa là thấy ngay) | **nướng trong image** (phải build lại) |
| Asset | Vite dev server (HMR) | `npm run build` sẵn trong image |
| Opcache | `validate_timestamps=1` | `validate_timestamps=0` |
| APP_ENV | local | staging, `APP_DEBUG=false` |
| DB/Redis/Meili | volume dev (chung Herd) | volume **riêng** cho staging |
| Đổi code | lưu file là xong | **build lại image** (mục dưới) |

---

## Lần đầu dựng

**1. Đặt APP_KEY** (image không có `.env` nên phải truyền qua env). Lấy key sẵn
trong `.env` dev, hoặc sinh mới `php artisan key:generate --show`:

```powershell
# PowerShell
$env:STAGING_APP_KEY = "base64:xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx="
```
```bash
# bash
export STAGING_APP_KEY="base64:xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx="
```

**2. Build image:**

```bash
docker compose -f docker-compose.staging.yml build
```

**3. QUAN TRỌNG — bring-up theo thứ tự: infra TRƯỚC, app SAU.**

App boot cần schema DB có sẵn (`AppServiceProvider::registerObservers()` +
trait `HasSchemaCache` chạy `DESCRIBE` bảng ngay lúc boot → `config:cache` trong
entrypoint sẽ chết nếu DB rỗng → crash-loop). Nên phải nạp DB **trước khi** app
container start:

```bash
# 3a. Chỉ dựng hạ tầng (mysql/redis/meili) — DB start ở trạng thái rỗng
docker compose -f docker-compose.staging.yml up -d mysql redis meilisearch

# 3b. Import dump (schema + data) vào DB staging riêng
docker compose -f docker-compose.staging.yml exec -T mysql \
  mariadb -uroot -proot infun < infun_xampp.sql

# 3c. GIỜ mới dựng app (config:cache tìm thấy schema → boot OK)
docker compose -f docker-compose.staging.yml up -d
```

> Không có dump và muốn schema mới từ migration? Thay 3a–3c bằng một lệnh —
> `RUN_MIGRATIONS=1` để entrypoint migrate TRƯỚC khi cache (đã xử lý trong
> `entrypoint.staging.sh`):
> ```bash
> RUN_MIGRATIONS=1 docker compose -f docker-compose.staging.yml up -d
> ```

**4. Đánh index search** (một lần, sau khi có data):

```bash
docker compose -f docker-compose.staging.yml exec infun-php \
  php artisan scout:import "App\Models\Entities\Product"
```

Mở `http://localhost:8100` — thẻ `<base>` sẽ tự ra `http://localhost:8100/`.

---

## Đẩy code từ folder lên staging (vòng lặp chính)

Vì image bất biến, **không có sync/rsync/scp**. "Đẩy code" = build lại image:
Docker lấy chính folder này làm *build context*, `COPY` code vào image mới, rồi
recreate container. Một lệnh:

```bash
docker compose -f docker-compose.staging.yml build \
  && docker compose -f docker-compose.staging.yml up -d
```

- `build` đọc code + chạy `npm run build` + `composer install --no-dev` → image mới.
- `up -d` thấy image đổi → recreate `infun-php` / `infun-queue` / `infun-web` với
  code mới. DB/Redis/Meili giữ nguyên (volume không mất).
- Entrypoint tự chạy lại `config:cache` / `route:cache` / `view:cache` theo ENV.

Sửa code xong chỉ cần chạy lại 1 lệnh trên. **Không cần** `down -v` (đừng, sẽ mất DB).

> Build nhanh hơn nhờ layer cache: đổi code PHP thì stage `assets`
> (`npm run build`) và bước cài vendor thường được cache lại nếu
> `package.json` / `composer.json` không đổi.

---

## Lệnh hay dùng

```bash
# Xem log
docker compose -f docker-compose.staging.yml logs -f infun-php

# Vào shell app
docker compose -f docker-compose.staging.yml exec infun-php sh

# Migration khi deploy code có thay đổi schema
docker compose -f docker-compose.staging.yml exec infun-php php artisan migrate --force

# 3 PHP worker sau LB để test tải (nginx least_conn tự chia)
docker compose -f docker-compose.staging.yml up -d --scale infun-php=3

# Dừng (GIỮ dữ liệu)
docker compose -f docker-compose.staging.yml down

# Xoá sạch cả DB/Redis/Meili staging (làm lại từ đầu)
docker compose -f docker-compose.staging.yml down -v
```

---

## Base URL trên staging

- TOÀN BỘ base URL (thẻ `<base>`, `publicUrl()`, mail, sitemap, queue) lấy từ
  `config('app.url')` = `APP_URL` — KHÔNG còn bám host request (đã đổi ở
  `head.blade.php` + `Common.php` để HTML host-independent, cache/CDN an toàn).
- Hệ quả: mỗi môi trường có 1 host canonical. Truy cập staging bằng host khác
  default (`http://localhost:8100`) — LAN IP hay domain riêng — thì BẮT BUỘC
  set `STAGING_APP_URL` khớp trước khi `up`:

```powershell
$env:STAGING_APP_URL = "https://staging.infun.vn"
```

## Cổng (đổi nếu trùng)

| Dịch vụ | Cổng host mặc định | Biến override |
|---|---|---|
| Web | 8100 | `STAGING_WEB_PORT` |
| MySQL | 3307 | `STAGING_DB_PORT` |
| Meilisearch | 7701 | `STAGING_MEILI_PORT` |
| Mailpit UI | 8026 | `STAGING_MAIL_UI_PORT` |

Đặt để tránh đụng stack dev (3306/7700/8025) khi chạy song song.
