# Production trên AWS — Setup & CI/CD (infun)

> Runbook dựng môi trường **production** cho infun trên AWS (EC2 + Docker Compose,
> RDS MariaDB, ElastiCache Valkey), phát hành qua **Cloudflare Tunnel** tới
> `https://tienpv.shop`, và pipeline **CI/CD staging → production có cổng phê duyệt**.
>
> Region: **ap-southeast-1 (Singapore)**. Ngày dựng: 2026-07-30.

---

## Tổng quan kiến trúc

```
Dev  ──push──►  GitHub (economer)  ──CI──►  build image  ──►  ghcr.io (:staging + :sha-XXX)
                                                                      │  deploy staging (máy nhà, Tailscale)
                        tạo Release vX.Y.Z + APPROVE  ─────────────┐  │
                                                                   ▼  ▼
                           promote :sha-XXX ──► :production  ──►  EC2 (Singapore)
                                                                   │
   Internet ──► Cloudflare (tienpv.shop, HTTPS) ──► cloudflared tunnel ──► nginx:8100 ──► php-fpm ×2
                                                                   │
                                          ┌────────────────────────┼───────────────────┐
                                          ▼                        ▼                    ▼
                                   RDS MariaDB 10.11        ElastiCache Valkey     Meilisearch (container)
                                   (DB)                     (cache/session/queue)  (Scout)
```

Điểm cốt lõi: **image bất biến**, DB/Redis dùng **managed service**, chỉ **1 tunnel outbound** ra Internet (không mở cổng web nào), phát hành = **promote đúng image đã test ở staging** (tested = shipped) sau khi **bấm duyệt**.

---

## Thông số hạ tầng (reference)

| Thành phần | Giá trị |
|---|---|
| EC2 Public IP | đổi khi stop/start hoặc rebuild — nên gán Elastic IP nếu cần cố định |
| EC2 Tailscale IP | xem `tailscale ip -4` trên máy, hoặc secret `PROD_HOST` — **đổi mỗi lần rebuild EC2**, xem §9 |
| EC2 OS / type | Ubuntu 24.04+ LTS / t3.small / 30GB gp3 |
| EC2 user / app dir | `ubuntu` / `/srv/infun` |
| RDS (MariaDB 10.11) | xem `.env` trên EC2 (`DB_HOST`) — đổi mỗi lần tạo lại RDS |
| RDS db / user | `infun` / `admin` |
| ElastiCache Valkey | xem `.env` trên EC2 (`REDIS_HOST`) — đổi mỗi lần tạo lại cache. **Phải là node-based, xem §4** |
| Domain | `tienpv.shop` (Cloudflare Tunnel, HTTPS) |
| Registry | `ghcr.io/phantien304/infun-app`, `ghcr.io/phantien304/infun-web` |
| Staging (nguồn data) | Tailscale `100.99.170.2`, user `an-my`, container `infun-mysql-staging` |

> Mật khẩu / token **không lưu trong file này**. Giữ ở nơi an toàn (password manager / GitHub Secrets / AWS Secrets Manager).

---

## 1. EC2

1. Console → region **Singapore (ap-southeast-1)**.
2. **Key pair:** EC2 → Key Pairs → Create → `infun-prod`, ED25519, `.pem` → tải về, `chmod 400`.
3. **Launch instance:**
   - AMI: Ubuntu Server **24.04 LTS**, kiến trúc **64-bit x86** (khớp image amd64).
   - Type: **t3.small** (2GB). Resize sau dễ: Stop → Change instance type → Start.
   - Security group `infun-prod-sg`: chỉ mở **SSH (22) từ My IP**. **KHÔNG** mở cổng web (dùng tunnel).
   - Storage: **30 GiB gp3**.
4. **Kết nối:** `ssh -i infun-prod.pem ubuntu@<PUBLIC_IP>` (hoặc EC2 Instance Connect trên Console).
5. (khuyến nghị, máy 2GB) thêm swap:
   ```bash
   sudo fallocate -l 2G /swapfile && sudo chmod 600 /swapfile
   sudo mkswap /swapfile && sudo swapon /swapfile
   echo '/swapfile none swap sw 0 0' | sudo tee -a /etc/fstab
   ```

---

## 2. Docker + Compose + Tailscale (trên EC2)

```bash
sudo apt-get update && sudo apt-get upgrade -y

# Docker Engine (repo chính thức)
sudo apt-get install -y ca-certificates curl
sudo install -m 0755 -d /etc/apt/keyrings
sudo curl -fsSL https://download.docker.com/linux/ubuntu/gpg -o /etc/apt/keyrings/docker.asc
sudo chmod a+r /etc/apt/keyrings/docker.asc
echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.asc] https://download.docker.com/linux/ubuntu $(. /etc/os-release && echo "$VERSION_CODENAME") stable" | sudo tee /etc/apt/sources.list.d/docker.list > /dev/null
sudo apt-get update
sudo apt-get install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
sudo usermod -aG docker $USER && newgrp docker

# Tailscale (để CI SSH vào không cần mở port 22 ra Internet)
curl -fsSL https://tailscale.com/install.sh | sh
sudo tailscale up          # mở URL, đăng nhập cùng tailnet với staging
tailscale ip -4            # -> 100.84.143.32 (dùng làm PROD_HOST cho CI)
```

---

## 3. RDS MariaDB

> **Bài học:** ban đầu tạo RDS **MySQL 8** nhưng app + staging + dev đều là **MariaDB** → import lỗi zero-date (1067) và runtime siết strict. Giải pháp: dùng **RDS MariaDB** cho khớp engine. Xem Phụ lục.

1. RDS → **Create database** → Full configuration.
2. Engine **MariaDB 10.11**, Template Free tier, id `infun-prod-mariadb`, user `admin` + mật khẩu mạnh, `db.t3.micro`, 20GB gp3.
3. **Connectivity:** chọn **"Connect to an EC2 compute resource" → `infun-prod`** (RDS tự tạo SG cho EC2 nối vào), **Public access: No**.
4. **Additional configuration → Initial database name:** `infun`.
5. Backup: retention **1 day** (giới hạn free plan; 7 ngày sẽ báo lỗi).
6. Deletion protection: tắt lúc dựng; bật lại khi ổn định.
7. Test từ EC2:
   ```bash
   mysql -h infun-prod-mariadb.c1emwwgs467b.ap-southeast-1.rds.amazonaws.com -u admin -p infun -e "SHOW DATABASES;"
   ```

---

## 4. ElastiCache Valkey (Redis-compatible)

> ⚠️ **BẮT BUỘC chọn đúng loại — bài học từ lần rebuild 2026-08-24/25.**
> `docker-compose.production.yml` chỉ khai báo `REDIS_HOST`/`REDIS_PORT` trần,
> **không** có chỗ cho TLS hay tách nhiều DB. Nó chỉ tương thích với
> **ElastiCache node-based, Cluster mode Disabled, Encryption in transit tắt**.
>
> **KHÔNG chọn "Serverless"** dù Console hay đưa lên làm lựa chọn nổi bật/mặc
> định — Serverless **bắt buộc TLS** (không tắt được) và **chỉ có 1 database**
> (không `SELECT` được), trong khi app tách connection `cache` (DB 1) khỏi
> `default`/session/queue (DB 0). Dùng nhầm Serverless sẽ khiến
> `php artisan optimize:clear` treo vô thời hạn ở bước `cache` lúc container
> boot — không lỗi, không log, rất khó đoán ra nguyên nhân nếu không biết
> trước điều này. Xem thêm Phụ lục.

1. EC2 → Security Groups → Create `infun-redis-sg`, inbound **Custom TCP 6379** source = SG của EC2 (`infun-prod-sg`).
2. ElastiCache → **Create cache** → Engine **Valkey** → Deployment option **Node-based cluster** → Creation method **Cluster cache** (KHÔNG chọn "Easy create" — nó có thể tự ép bật Encryption in transit, không cho tắt).
3. Configuration preset: **Demo** (`cache.t4g.micro`) hoặc tương đương nhỏ — **không** để mặc định "Production" (`cache.r8g.xlarge`, quá lớn/tốn kém cho quy mô này).
4. **Cluster mode: Disabled.**
5. Parameter group: chọn bản **không có hậu tố `.cluster.on`** — Console đôi khi giữ nguyên parameter group của Cluster mode Enabled dù đã đổi lựa chọn ở bước trên, phải tự kiểm tra lại.
6. Name `infun-prod-redis`, node nhỏ (`cache.t3.micro`/`cache.t4g.micro`), **replicas 0**, **Multi-AZ tắt**.
7. VPC ID: phải khớp đúng VPC của EC2.
8. Advanced settings → Security → **Encryption in transit: bỏ tick Enable** (đây là bước hay bị bỏ sót nhất).
9. Selected security groups: gán `infun-redis-sg` — **kiểm tra Inbound rules của chính SG đó có ít nhất 1 rule thật** (Custom TCP 6379, Source = SG của EC2) trước khi tin. SG do tính năng "Set up compute connection" tự tạo cho một resource khác **có thể có 0 Permission entries**, gán nhầm sẽ không có tác dụng gì dù trông như đã cấu hình xong.
10. Lấy **Primary endpoint** (bỏ `:6379`) → `REDIS_HOST`. Test:
    ```bash
    sudo apt-get install -y redis-tools
    redis-cli -h <primary-endpoint> -p 6379 ping   # PONG
    ```

---

## 5. Cấu hình app trên EC2 (compose + .env)

```bash
sudo mkdir -p /srv/infun && sudo chown $USER:$USER /srv/infun
git clone https://<GH_TOKEN>@github.com/phantien304/infun.git /srv/infun   # token: repo(read)+read:packages
cd /srv/infun
echo <GH_TOKEN> | docker login ghcr.io -u phantien304 --password-stdin      # để pull image riêng tư
cp .env.production.example .env
nano .env
```

`.env` (giá trị thật — không commit):

```
APP_KEY=base64:...                # echo "base64:$(openssl rand -base64 32)"
APP_URL=https://tienpv.shop
DB_HOST=infun-prod-mariadb.c1emwwgs467b.ap-southeast-1.rds.amazonaws.com
DB_DATABASE=infun
DB_USERNAME=admin
DB_PASSWORD=...
MYSQL_ATTR_SSL_CA=                 # trống = nối trong VPC (đủ an toàn)
REDIS_HOST=infun-prod-redis.carsnc.ng.0001.apse1.cache.amazonaws.com
REDIS_PASSWORD=
MEILISEARCH_KEY=...                # openssl rand -hex 16
AWS_ACCESS_KEY_ID=...              # R2 (copy từ .env dev)
AWS_SECRET_ACCESS_KEY=...
AWS_DEFAULT_REGION=auto
AWS_BUCKET=infun-media
AWS_ENDPOINT=https://<r2>.r2.cloudflarestorage.com
AWS_URL=https://cdn.lartisan.vn
LIMIT_ACCESS_ENABLED=false         # false = public; true + LIMIT_ACCESS_IPS=... = giới hạn IP
LIMIT_ACCESS_IPS=
PROD_PHP_REPLICAS=2
PROD_WEB_PORT=8100
```

File `docker-compose.production.yml` (trong repo) chạy: `infun-php ×2`, `infun-web` (nginx, publish 8100 localhost), `infun-queue`, `infun-scheduler`, `meilisearch`. **Không** có mysql/redis container (đã dùng managed).

---

## 6. Import dữ liệu từ staging vào RDS

App **đọc schema lúc boot** (observers khởi tạo model → `describe` bảng) nên **không boot được nếu DB trống** → phải nạp schema+data từ ngoài (không qua `artisan migrate`).

```bash
# Dump FULL từ staging (Tailscale) -> xử lý MariaDB->import -> đẩy vào RDS.
# sed: bỏ dòng "sandbox mode" của MariaDB + gỡ DEFINER (RDS không có quyền SUPER).
ssh an-my@100.99.170.2 \
  "docker exec infun-mysql-staging mysqldump -uroot -proot --single-transaction --routines --triggers infun" \
  | sed '/enable the sandbox mode/d; s/DEFINER=`[^`]*`@`[^`]*`//g' > ~/infun-full.sql

mysql -h infun-prod-mariadb.c1emwwgs467b.ap-southeast-1.rds.amazonaws.com -u admin -p infun < ~/infun-full.sql

# kiểm dung lượng (vài GB = có data; vài MB = mới schema)
mysql -h ...rds... -u admin -p -e \
"SELECT ROUND(SUM(data_length+index_length)/1024/1024,1) AS size_mb FROM information_schema.tables WHERE table_schema='infun';"
```

Bring app lên:

```bash
cd /srv/infun
docker pull ghcr.io/phantien304/infun-app:staging
docker pull ghcr.io/phantien304/infun-web:staging
docker tag ghcr.io/phantien304/infun-app:staging ghcr.io/phantien304/infun-app:production
docker tag ghcr.io/phantien304/infun-web:staging ghcr.io/phantien304/infun-web:production
docker compose -f docker-compose.production.yml up -d --force-recreate
docker compose -f docker-compose.production.yml exec infun-php php artisan up   # nếu lỡ ở maintenance
curl -s -H "X-Forwarded-Proto: https" http://127.0.0.1:8100/ | head -20         # ra HTML infun = OK
```

> Health check local phải kèm `-H "X-Forwarded-Proto: https"`, nếu không middleware `HttpsProtocol` trả **302** (không phải lỗi).

---

## 7. Domain + HTTPS (Cloudflare Tunnel)

Cloudflare proxy **không hỗ trợ cổng gốc 8100** → dùng **Tunnel** (giữ 8100, có HTTPS, không mở port).

```bash
# cài cloudflared
curl -L https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-linux-amd64.deb -o cloudflared.deb
sudo dpkg -i cloudflared.deb
cloudflared tunnel login                 # chọn zone tienpv.shop
cloudflared tunnel create infun-prod     # ghi TUNNEL_ID

# config
mkdir -p ~/.cloudflared
cat > ~/.cloudflared/config.yml <<EOF
tunnel: <TUNNEL_ID>
credentials-file: /home/ubuntu/.cloudflared/<TUNNEL_ID>.json
ingress:
  - hostname: tienpv.shop
    service: http://localhost:8100
  - service: http_status:404
EOF

cloudflared tunnel route dns infun-prod tienpv.shop   # nếu báo record đã tồn tại: xóa A record cũ ở CF DNS rồi chạy lại

# chạy như systemd service (config phải nằm ở /etc/cloudflared vì service chạy bằng root)
sudo mkdir -p /etc/cloudflared
sudo cp ~/.cloudflared/config.yml /etc/cloudflared/config.yml
sudo cp ~/.cloudflared/*.json /etc/cloudflared/
TUNNEL_ID=$(grep '^tunnel:' /etc/cloudflared/config.yml | awk '{print $2}')
sudo sed -i "s#credentials-file:.*#credentials-file: /etc/cloudflared/$TUNNEL_ID.json#" /etc/cloudflared/config.yml
sudo cloudflared service install
sudo systemctl enable --now cloudflared
sudo systemctl status cloudflared --no-pager
```

> App đã có `trustProxies(at: '*')` trong `bootstrap/app.php` nên nhận đúng HTTPS sau Cloudflare (link/redirect ra https).

---

## 8. Cổng chặn IP (middleware LimitAccess)

Quản qua `.env` (không sửa code):
- `LIMIT_ACCESS_ENABLED=false` → **mở public** (mặc định).
- `LIMIT_ACCESS_ENABLED=true` + `LIMIT_ACCESS_IPS=1.2.3.4,2001:...` → chỉ các IP đó vào được (chỉ áp dụng ở production).

Lấy IP: `curl -s ifconfig.me`. Đổi xong: `docker compose -f docker-compose.production.yml up -d --force-recreate`.

---

## 9. CI/CD tự động: staging → production (có cổng duyệt)

**Files:** `.github/workflows/deploy-staging.yml` (build + đẩy `:staging` và `:sha-<commit>`), `.github/workflows/deploy-production.yml` (promote + deploy), `deploy/prod-deploy.sh` (chạy trên EC2).

**Thiết lập một lần:**

1. **Environment `production` (cổng duyệt):** repo → Settings → Environments → New `production` → bật **Required reviewers** (thêm bạn). Cả 2 job trong `deploy-production.yml` gắn `environment: production` nên sẽ **treo chờ Approve**.

2. **Xác thực SSH của CI qua Tailscale ACL (không dùng SSH key riêng).** Workflow kết nối tailnet với `tags: tag:ci` (bước "Connect to Tailscale"), sau đó SSH thẳng vào EC2 — **không** có bước tạo/dùng key `.pem` nào nữa. Cần cấu hình **1 lần trên Tailscale admin console** (`https://login.tailscale.com/admin`):

   a. **Settings → Tags** → Create tag → tên `tag:prod-ec2`, owner `autogroup:admin`.

   b. Gán tag này cho EC2 production: **Machines** → chọn máy → **Machine settings → Tags** → thêm `tag:prod-ec2`.

   c. **Policies → Tailscale SSH → Add rule**: Sources `tag:ci`, destinations `tag:prod-ec2`, as user `ubuntu`. Giữ nguyên rule mặc định `autogroup:member → autogroup:self` (vẫn cần để tự SSH vào máy của mình bình thường), chỉ thêm rule mới song song.

   > ⚠️ **Tag không tự mang sang khi rebuild EC2.** Mỗi lần tạo lại EC2 (máy mới, node mới trong tailnet), phải lặp lại bước (b) — gán lại `tag:prod-ec2` cho máy mới. Thiếu bước này, CI sẽ báo lỗi `tailscale: tailnet policy does not permit you to SSH to this node` dù mọi thứ khác đều đúng — xem Phụ lục.

3. **Secrets** (Settings → Secrets and variables → Actions): `PROD_HOST` = Tailscale IP của EC2 (`tailscale ip -4`), `PROD_SSH_USER=ubuntu`. **Không cần `PROD_SSH_KEY`** nữa. (`TS_OAUTH_CLIENT_ID`, `TS_OAUTH_SECRET` dùng lại từ staging.)
4. `deploy-production.yml` phải nằm ở **nhánh mặc định** của repo (workflow release/manual chạy theo default branch).
5. Khi EC2 đổi Tailscale IP (rebuild máy), nhớ cập nhật secret `PROD_HOST` — cả `infun` **và** `infuncms` nếu 2 repo cùng deploy lên máy này, cùng cần lặp lại bước 2(b) ở trên cho tailnet (chỉ cần làm 1 lần, dùng chung cho cả 2 repo vì cùng SSH vào 1 EC2).

**Quy trình phát hành:**

1. Push code lên `economer` → staging CI build `:staging` + `:sha-<commit>` và deploy staging (tự động).
2. Kiểm tra staging OK → GitHub **Releases → Draft new release** → tag `vX.Y.Z` trỏ commit đó → Publish.
3. Workflow **Deploy production** chạy, **dừng ở cổng duyệt** → Actions → Review deployments → **Approve**.
4. Sau approve: `promote` retag `:sha-<commit>` → `:production` (không build lại) → `deploy` SSH vào EC2 (Tailscale) chạy `prod-deploy.sh`: `git checkout vX.Y.Z` → `docker compose pull` → `up` → `migrate --force` → health check.

**Rollback:** Actions → Deploy production → **Run workflow** → nhập `sha` bản tốt cũ → Approve (retag `:production` về bản cũ + deploy lại). Nếu có đổi schema, khôi phục snapshot RDS.

**Backup trước migrate (khuyến nghị):** tạo `/etc/infun-deploy.env` trên EC2 (script tự đọc):
```bash
APP_DIR=/srv/infun
COMPOSE_FILE=docker-compose.production.yml
HEALTH_URL=http://127.0.0.1:8100/
PRE_DEPLOY_HOOK="aws rds create-db-snapshot --db-instance-identifier infun-prod-mariadb --db-snapshot-identifier infun-$(date +%Y%m%d%H%M%S)"
```
(cần AWS CLI + IAM role gắn EC2 có quyền `rds:CreateDBSnapshot`.)

---

## 10. Vận hành

- **Xem trạng thái:** `docker compose -f docker-compose.production.yml ps` (trong `/srv/infun`).
- **Log app:** `docker compose -f docker-compose.production.yml logs infun-php --tail=50`.
- **Health local:** `curl -I -H "X-Forwarded-Proto: https" http://127.0.0.1:8100/` (kỳ vọng 200).
- **Resize EC2:** Stop → Change instance type → Start (image kéo lại từ registry; **Public IP đổi** trừ khi có Elastic IP; **Tailscale IP giữ nguyên** nên CI không ảnh hưởng).
- **Soi DB bằng GUI** (máy Windows) qua SSH tunnel:
  ```powershell
  ssh -i "$HOME\.ssh\infun-prod.pem" -L 3307:infun-prod-mariadb.c1emwwgs467b.ap-southeast-1.rds.amazonaws.com:3306 ubuntu@13.229.118.236
  # rồi GUI nối 127.0.0.1:3307 (admin / infun)
  ```

---

## Phụ lục — Các lỗi đã gặp & cách xử lý

| Triệu chứng | Nguyên nhân | Cách xử lý |
|---|---|---|
| php container **Restarting**, log `getaddrinfo ... redis ... Name does not resolve` | `.env` còn placeholder `xxxxxx` ở `REDIS_HOST`/`DB_HOST` | Điền endpoint thật rồi `up -d --force-recreate` |
| Import `ERROR 1067 Invalid default value for 'date_start'` | Dump MariaDB có default `0000-00-00`, **MySQL 8** cấm | **Dùng RDS MariaDB** thay vì MySQL (khớp engine) |
| Import `ERROR 1227 ... need SUPER, SET USER privilege` | Trigger/view có `DEFINER=` mà RDS không cấp SUPER | `sed 's/DEFINER=\`[^\`]*\`@\`[^\`]*\`//g'` trước khi import |
| MySQL báo lỗi ngay dòng đầu dump | Dòng "enable the sandbox mode" của MariaDB mysqldump | `sed '/enable the sandbox mode/d'` |
| App boot chết `Table 'infun.review' doesn't exist` | Import **schema-only**, app introspect schema lúc boot | Import **cả data** (bỏ `--no-data`) |
| Trang ra **"COME HERE BABY"** (503) | Middleware `LimitAccess` chặn IP ở production | Đặt `LIMIT_ACCESS_ENABLED=false` (hoặc thêm IP) trong `.env` + recreate |
| `curl http://127.0.0.1:8100` ra **302 → https** | Middleware `HttpsProtocol` ép HTTPS | Bình thường; health check thêm `-H "X-Forwarded-Proto: https"` |
| `cloudflared`: *Cannot determine default configuration path* | Chạy `sudo` nên tìm config ở `/root`, `/etc` | Copy config + creds vào `/etc/cloudflared/` |
| `tunnel route dns`: *record ... already exists* | Đã có A/CNAME cho `tienpv.shop` | Xóa record cũ ở Cloudflare DNS rồi chạy lại |
| Cloudflare không proxy được `:8100` | CF chỉ proxy origin ở 80/443/… (không có 8100) | Dùng **Cloudflare Tunnel** (đã áp dụng) |
| RDS create lỗi *backup retention exceeds free tier* | Free plan giới hạn retention | Đặt retention **1 day** hoặc tắt automated backup |
| `ssh: connect ... port 22: Connection timed out` dù key đúng | Security Group chưa có rule nào (kiểm tra Inbound rules thường thấy "No rules to display") — AWS âm thầm drop gói tin, không phải lỗi key/IP | Thêm rule SSH (22) Source = My IP, tạm thời trong lúc setup |
| `php artisan optimize:clear`/`cache:clear` treo vô thời hạn, không lỗi, không log | ElastiCache đang là **Serverless** (bắt buộc TLS) trong khi app kết nối trần | Đổi sang ElastiCache **node-based, Cluster mode Disabled, No TLS** — xem §4. Không tự vá TLS/DB trong compose, dễ lệch khỏi thiết kế gốc |
| `RedisException: read error ... at Redis->select(1)` | ElastiCache Serverless chỉ có 1 database, không `SELECT` được, trong khi connection `cache` dùng `REDIS_CACHE_DB=1` | Cùng hướng xử lý §4 — chuyển hẳn sang node-based, không patch DB index |
| Gán Security Group cho ElastiCache mà vẫn không kết nối được, dù trông "đã cấu hình" | SG đó do tính năng "Set up compute connection" tự tạo cho resource khác — vào thẳng SG sẽ thấy **"Inbound rules count: 0 Permission entries"** | Tạo Security Group thủ công, thêm rule thật (Custom TCP 6379, Source = SG của EC2), gán SG đó thay vì SG tự sinh |
| CI báo `tailscale: tailnet policy does not permit you to SSH to this node`, cả `infun` lẫn `infuncms` cùng lỗi | EC2 mới (rebuild) chưa có tag mà rule ACL SSH yêu cầu | Gán lại `tag:prod-ec2` cho EC2 mới + xác nhận rule `tag:ci → tag:prod-ec2` còn tồn tại — xem §9 mục 2 |
| Deploy xong nhưng code không phải bản mới nhất trên `main` | `infuncms` (SPA, build theo build-arg) chỉ build lại production khi có **GitHub Release** mới — push `main` không tự trigger | Vào Releases → Draft new release → tag mới → Publish, rồi Approve ở cổng duyệt |
