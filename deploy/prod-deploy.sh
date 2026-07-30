#!/usr/bin/env bash
#
# Chạy TRÊN máy EC2 production (được deploy-production.yml đẩy qua SSH stdin):
#     bash -s -- <git_ref> <image_tag>
#
#   <git_ref>   = tag/commit để đồng bộ mã nguồn (compose file, script, migration).
#   <image_tag> = tag image sẽ chạy (workflow luôn truyền "production").
#
# Cấu hình riêng của máy đặt ở /etc/infun-deploy.env (không commit) — ví dụ:
#     APP_DIR=/srv/infun
#     COMPOSE_FILE=docker-compose.production.yml
#     HEALTH_URL=http://127.0.0.1:8100/
#     PRE_DEPLOY_HOOK="aws rds create-db-snapshot ..."   # tùy chọn: snapshot trước migrate
set -euo pipefail

REF="${1:?thiếu git ref}"
IMAGE_TAG="${2:-production}"

# Nạp cấu hình máy (nếu có) rồi áp default.
[ -f /etc/infun-deploy.env ] && . /etc/infun-deploy.env
APP_DIR="${APP_DIR:-/srv/infun}"
COMPOSE_FILE="${COMPOSE_FILE:-docker-compose.production.yml}"
HEALTH_URL="${HEALTH_URL:-http://127.0.0.1:8100/}"
PULL_SERVICES="${PULL_SERVICES:-infun-php infun-web}"
UP_SERVICES="${UP_SERVICES:-infun-php infun-web infun-queue infun-scheduler}"

log() { echo "[prod-deploy] $*"; }

cd "$APP_DIR"

log "Đồng bộ mã nguồn -> $REF"
git fetch --all --tags --prune
git checkout -f "$REF"

# ── Backup TRƯỚC migrate (bắt buộc trên prod) ──────────────────────────────────
# Đặt lệnh snapshot vào PRE_DEPLOY_HOOK trong /etc/infun-deploy.env. Nếu bỏ trống,
# script CHỈ nhắc — hãy chắc chắn bạn có cơ chế backup RDS (automated backups/snapshot).
if [ -n "${PRE_DEPLOY_HOOK:-}" ]; then
  log "Chạy PRE_DEPLOY_HOOK (backup)…"
  bash -c "$PRE_DEPLOY_HOOK"
else
  log "⚠️  PRE_DEPLOY_HOOK trống — đảm bảo RDS đã có snapshot/automated backup trước khi migrate."
fi

log "Kéo image tag :$IMAGE_TAG"
docker compose -f "$COMPOSE_FILE" pull $PULL_SERVICES

log "Recreate container"
docker compose -f "$COMPOSE_FILE" up -d --no-build $UP_SERVICES

log "Chạy migration"
docker compose -f "$COMPOSE_FILE" exec -T infun-php php artisan migrate --force

# ── Health check ──────────────────────────────────────────────────────────────
# Thất bại -> exit 1 -> job GitHub đỏ. Rollback: re-run workflow với SHA tốt trước đó
# (xem docs/CI-CD-PRODUCTION.md).
log "Health check $HEALTH_URL"
ok=0
for i in $(seq 1 10); do
  code="$(curl -s -o /dev/null -w '%{http_code}' -H 'X-Forwarded-Proto: https' "$HEALTH_URL" || true)"
  if [ "$code" = "200" ]; then log "health OK (HTTP 200)"; ok=1; break; fi
  log "health HTTP $code — thử lại lần $i/10"; sleep 3
done
[ "$ok" = "1" ] || { log "❌ HEALTH CHECK THẤT BẠI — cần rollback"; exit 1; }

docker image prune -f
log "✅ Deploy production hoàn tất ($REF)"
