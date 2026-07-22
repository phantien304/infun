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

## Bring-up qua remote context (từ máy dev, `docker context ... ssh://...`)

`mysql-replica1`/`mysql-replica2`/`proxysql` có bind-mount file cấu hình
(`./docker/mysql/replica-init.sh`, `./docker/proxysql/proxysql.cnf`). Khi
`docker compose` chạy nhắm remote context, path `./...` được daemon REMOTE
diễn giải trên **ổ đĩa của chính nó** — không phải ổ đĩa máy dev. Máy staging
(192.168.1.11) có sẵn 1 bản mirror project đầy đủ (git, cùng commit) tại
`D:\projects\infun` — dùng path đó qua biến `STAGING_HOST_CONFIG_DIR`:

```bash
# PowerShell: $env:STAGING_HOST_CONFIG_DIR = "D:/projects/infun"
export STAGING_HOST_CONFIG_DIR="D:/projects/infun"
docker --context staging-ssh compose -f docker-compose.staging.yml up -d --scale infun-php=3
```

Không set biến này → mặc định `.` (build/bring-up local hoặc chạy trực tiếp
trên máy staging qua RDP/console, không đổi hành vi cũ). Mirror ở
`D:\projects\infun` nên `git pull` định kỳ để 2 file cấu hình trên không bị
lệch code so với máy dev.

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
| MySQL (writer) | 3307 | `STAGING_DB_PORT` |
| MySQL replica 1 | 3311 | `STAGING_DB_REPLICA1_PORT` |
| MySQL replica 2 | 3312 | `STAGING_DB_REPLICA2_PORT` |
| ProxySQL admin | 6032 | `STAGING_PROXYSQL_ADMIN_PORT` |
| ProxySQL write | 6033 | `STAGING_PROXYSQL_WRITE_PORT` |
| ProxySQL read | 6034 | `STAGING_PROXYSQL_READ_PORT` |
| Meilisearch | 7701 | `STAGING_MEILI_PORT` |
| Mailpit UI | 8026 | `STAGING_MAIL_UI_PORT` |

Đặt để tránh đụng stack dev (3306/7700/8025) khi chạy song song.

---

## DB Read Replica + ProxySQL (2026-07-21)

Staging hỗ trợ **1 write + 2 read replica** qua ProxySQL — cùng pattern đã
kiểm chứng ở `docker-compose.scale.yml` (dev stack), copy sang đây tái sử
dụng nguyên `docker/mysql/replica-init.sh` + `docker/proxysql/proxysql.cnf`
(không sửa gì 2 file đó — chúng hard-code tên service `mysql`/`mysql-replica1`/
`mysql-replica2`, service key trong `docker-compose.staging.yml` đã khớp).
`config/database.php` không cần sửa — đã có sẵn logic đọc `DB_WRITE_HOST`/
`DB_WRITE_PORT`/`DB_READ_HOST1`/`DB_READ_PORT`.

**⚠️ Đây là tính năng LUÔN BẬT trong `docker-compose.staging.yml`** (không có
cờ tắt riêng) — `x-app-env` mặc định trỏ `DB_WRITE_HOST=proxysql`/
`DB_READ_HOST1=proxysql`. Muốn quay lại 1 DB đơn (bỏ qua ProxySQL) thì đổi
tạm 2 dòng đó trong `x-app-env` về `DB_HOST`/`DB_PORT` (bỏ `DB_WRITE_HOST`/
`DB_READ_HOST1`) rồi recreate `infun-php`/`infun-queue`/`infun-scheduler` —
`mysql-replica1`/`mysql-replica2`/`proxysql` vẫn chạy nền, không ảnh hưởng gì
(có thể `docker compose stop mysql-replica1 mysql-replica2 proxysql` nếu
muốn tiết kiệm tài nguyên máy).

### Bring-up lần đầu (DB đã có data thật — theo thứ tự, KHÔNG gộp `up -d`)

```bash
# 1. Backup trước khi đổi gì
docker compose -f docker-compose.staging.yml exec -T mysql \
  mariadb-dump -uroot -proot --single-transaction --routines --events --triggers infun \
  > infun_staging_backup_$(date +%Y%m%d).sql

# 2. Recreate CHỈ mysql (thêm flag replication — server-id/log-bin/gtid-domain-id).
#    Volume staging_mysql_data GIỮ NGUYÊN (gắn theo tên, không theo container
#    instance) — đây KHÔNG phải reset data.
docker compose -f docker-compose.staging.yml up -d mysql
docker compose -f docker-compose.staging.yml ps mysql   # chờ healthy

# 3. Replica TUẦN TỰ — không song song (tránh dồn tải dump lên master 2 lần
#    cùng lúc). DB lớn (~vài GB) → dump+import lần đầu có thể mất nhiều phút.
docker compose -f docker-compose.staging.yml up -d mysql-replica1
docker compose -f docker-compose.staging.yml logs -f mysql-replica1
# chờ tới khi thấy "Slave_IO_Running: Yes" / "Slave_SQL_Running: Yes", healthy
docker compose -f docker-compose.staging.yml up -d mysql-replica2
docker compose -f docker-compose.staging.yml logs -f mysql-replica2
# chờ tương tự

# 4. ProxySQL (đợi cả 2 replica healthy)
docker compose -f docker-compose.staging.yml up -d proxysql

# 5. Recreate tầng app để nhận DB_WRITE_HOST/DB_READ_HOST1 mới
docker compose -f docker-compose.staging.yml up -d infun-php infun-queue infun-scheduler
```

**Tuyệt đối không `down -v` ở bất kỳ bước nào** — xoá cả `staging_mysql_data`
(data thật). Lỗi 1 replica giữa chừng: chỉ `stop`/`rm -f`/`volume rm` riêng
replica đó, không đụng `mysql`.

### Verify

```bash
# 1. Replication mỗi replica
docker compose -f docker-compose.staging.yml exec mysql-replica1 \
  mariadb -uroot -proot -e "SHOW SLAVE STATUS\G" | grep -E "Slave_IO_Running|Slave_SQL_Running|Seconds_Behind_Master|Last_Error"
# (lặp cho mysql-replica2) — Running: Yes cả 2, Last_Error rỗng

# 2. ProxySQL nhận đúng writer/reader
docker compose -f docker-compose.staging.yml exec proxysql \
  mysql -h127.0.0.1 -P6032 -uradmin -pradmin \
  -e "SELECT hostgroup, srv_host, status FROM stats_mysql_connection_pool;"
# HG10 = mysql ONLINE; HG20 = mysql-replica1 + mysql-replica2 ONLINE

# 3. Laravel thấy đúng 2 connection khác nhau
docker compose -f docker-compose.staging.yml exec infun-php php artisan tinker --execute="dd(DB::connection()->getPdo()->query('select @@hostname')->fetchColumn(), DB::connection()->getReadPdo()->query('select @@hostname')->fetchColumn());"
```

### Rủi ro cần nhớ

- `mariadb-dump --single-transaction` không khoá bảng nhưng vẫn tốn CPU/IO
  trên writer đang chạy — nên làm lúc ít traffic staging.
- `--log-bin` trên writer ghi binlog liên tục (`expire-logs-days=3`) — theo
  dõi dung lượng đĩa vài ngày đầu.
- `--scale infun-php=N` bị reset về 1 instance nếu chỉ `up -d infun-php` mà
  không kèm `--scale` — nhớ thêm lại `--scale infun-php=3` (hay N tuỳ trước
  đó) ở bước recreate app nếu đang test tải.
