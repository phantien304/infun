#!/usr/bin/env bash
# ═══════════════════════════════════════════════════════════════════════════
# Helper cho stack staging (image bất biến). Dùng bash / WSL / git-bash.
# PowerShell: gõ trực tiếp lệnh docker compose (xem docs/STAGING.md).
#
#   ./staging.sh deploy      # build lại image từ code hiện tại + up -d  ← đẩy code
#   ./staging.sh up          # up -d (không build)
#   ./staging.sh build       # chỉ build image
#   ./staging.sh logs [svc]  # theo dõi log (mặc định infun-php)
#   ./staging.sh migrate     # php artisan migrate --force
#   ./staging.sh reindex     # scout:import Product
#   ./staging.sh shell       # vào shell container app
#   ./staging.sh scale N     # up -d --scale infun-php=N
#   ./staging.sh down        # dừng (giữ dữ liệu)
#   ./staging.sh reset       # down -v (XOÁ DB/Redis/Meili staging)
# ═══════════════════════════════════════════════════════════════════════════
set -euo pipefail
cd "$(dirname "$0")"

FILE="docker-compose.staging.yml"
DC="docker compose -f $FILE"

if [ -z "${STAGING_APP_KEY:-}" ]; then
    echo "⚠  Chưa set STAGING_APP_KEY. Lấy từ .env dev hoặc: php artisan key:generate --show"
    echo "    export STAGING_APP_KEY=\"base64:....=\""
    [ "${1:-}" != "logs" ] && [ "${1:-}" != "down" ] && exit 1
fi

cmd="${1:-deploy}"
case "$cmd" in
    build)   $DC build ;;
    up)      $DC up -d ;;
    deploy)  $DC build && $DC up -d && echo "✔ Đã đẩy code mới lên staging." ;;
    logs)    $DC logs -f "${2:-infun-php}" ;;
    migrate) $DC exec infun-php php artisan migrate --force ;;
    reindex) $DC exec infun-php php artisan scout:import "App\\Models\\Entities\\Product" ;;
    shell)   $DC exec infun-php sh ;;
    scale)   $DC up -d --scale "infun-php=${2:-3}" ;;
    down)    $DC down ;;
    reset)   $DC down -v ;;
    *)       echo "Lệnh không hợp lệ: $cmd (build|up|deploy|logs|migrate|reindex|shell|scale|down|reset)"; exit 1 ;;
esac
