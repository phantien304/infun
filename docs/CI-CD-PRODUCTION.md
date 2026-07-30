# CI/CD Production (AWS EC2 + Docker Compose)

Luồng phát hành đầy đủ: **dev → PR (test gate) → staging → production (promote)**.
Production KHÔNG build lại — nó **promote đúng image `:sha-XXX` đã chạy ở staging**
(tested = shipped), qua một **cổng phê duyệt thủ công**.

```
push economer ─▶ build (:staging + :sha-XXX) ─▶ deploy staging        (tự động)
                                                       │
                         QA OK ─▶ tạo GitHub Release vX.Y.Z
                                                       │
             [Environment production: CHỜ APPROVE]  ◀──┘
                                                       │
        promote :sha-XXX ─▶ :production (retag, không build lại)
                                                       │
        SSH (Tailscale) ─▶ EC2: pull :production ─▶ up ─▶ migrate ─▶ health check
```

## File liên quan

| File | Vai trò |
|---|---|
| `.github/workflows/deploy-staging.yml` | Đã sửa: build đẩy thêm tag bất biến `:sha-<commit>` |
| `.github/workflows/deploy-production.yml` | Mới: promote (cổng duyệt) + deploy EC2 |
| `deploy/prod-deploy.sh` | Script chạy trên EC2: pull / up / migrate / health check |

## 1. Thiết lập trên GitHub (một lần)

**Environment `production` (đây chính là cổng phê duyệt):**
Settings → Environments → New environment → `production` → bật **Required reviewers**
(chọn bạn / người duyệt). Từ đó job `promote`/`deploy` sẽ treo chờ tới khi được Approve.

**Secrets** (Settings → Secrets and variables → Actions):

| Secret | Giá trị |
|---|---|
| `TS_OAUTH_CLIENT_ID`, `TS_OAUTH_SECRET` | Đã có sẵn (dùng chung với staging) |
| `PROD_HOST` | IP Tailscale (hoặc hostname) của EC2 production |
| `PROD_SSH_USER` | User SSH trên EC2 (vd `ubuntu`) |
| `PROD_SSH_KEY` | Private key SSH (ed25519) để vào EC2 |

> Ảnh (image) mặc định đẩy lên `ghcr.io/phantien304/*`. Nếu đổi registry/owner,
> sửa `env.IMAGE_APP` / `env.IMAGE_WEB` trong cả hai workflow.

## 2. Chuẩn bị EC2 production (một lần — phần hạ tầng bạn tự lo)

1. Cài **Docker + Docker Compose plugin** và **Tailscale** (join tailnet, để CI SSH
   vào qua IP `100.x` — không cần mở port 22 ra Internet).
2. Đăng nhập registry để `pull` được image riêng tư:
   `echo <GHCR_READ_TOKEN> | docker login ghcr.io -u <user> --password-stdin`
   (hoặc để package ở chế độ public thì bỏ qua).
3. Clone repo vào `APP_DIR` (mặc định `/srv/infun`).
4. Tạo **`docker-compose.production.yml`** — giống staging nhưng:
   - image trỏ tag `:production`, ví dụ `image: ghcr.io/phantien304/infun-app:production`;
   - **bỏ** service `mysql` / `redis` / lưu-file-local — thay bằng **RDS / ElastiCache / S3**
     (cấu hình endpoint + credential qua `.env`).
5. Tạo `.env` production (APP_URL thật, DB host = RDS, REDIS host = ElastiCache, S3…).
6. (Tùy chọn nhưng khuyến nghị) tạo `/etc/infun-deploy.env`:
   ```bash
   APP_DIR=/srv/infun
   COMPOSE_FILE=docker-compose.production.yml
   HEALTH_URL=http://127.0.0.1:8100/
   # Snapshot RDS trước migrate (cần AWS CLI + IAM trên máy):
   PRE_DEPLOY_HOOK="aws rds create-db-snapshot --db-instance-identifier infun-prod --db-snapshot-identifier infun-$(date +%Y%m%d%H%M%S)"
   ```

## 3. Phát hành một bản lên production

1. Xác nhận commit đã chạy ổn trên **staging** (nhánh `economer`).
2. Trên GitHub → **Releases → Draft a new release** → tạo tag `vX.Y.Z` trỏ đúng commit đó → Publish.
3. Workflow `Deploy production` chạy và **dừng ở cổng duyệt** → vào tab Actions bấm **Approve**.
4. Sau approve: retag `:sha` → `:production`, SSH vào EC2, pull + up + migrate + health check.

> Không muốn dùng Release? Có thể vào **Actions → Deploy production → Run workflow**
> và nhập trực tiếp `sha` của commit muốn phát hành.

## 4. Rollback

Vì mỗi bản là một tag image bất biến, rollback = **phát hành lại bản cũ**:

**Actions → Deploy production → Run workflow → nhập `sha` của bản tốt trước đó** → Approve.

Nó retag `:production` về image cũ và deploy lại. (Nếu rollback có kèm hoàn tác schema DB,
khôi phục từ snapshot RDS đã tạo ở `PRE_DEPLOY_HOOK`.)

## 5. Lưu ý migration trên prod

- Luôn có **snapshot/backup RDS trước migrate** (`PRE_DEPLOY_HOOK` hoặc automated backups).
- Viết migration **tương thích ngược (expand/contract)**: thêm cột/bảng ở bản này, xóa ở
  bản sau — để container cũ và mới cùng chạy được trong lúc recreate, tránh downtime.
