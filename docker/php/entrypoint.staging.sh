#!/bin/sh
# ═══════════════════════════════════════════════════════════════════════════
# Entrypoint STAGING — chạy khi container start (code đã nướng trong image).
#
# Vì opcache.validate_timestamps=0, cache phải build bằng ENV RUNTIME (do
# docker-compose inject: APP_URL, DB_*, REDIS_*...) — KHÔNG build lúc `docker
# build` vì lúc đó chưa có secret/host thật.
#
# Dùng chung cho cả app (php-fpm), queue (queue:work) và scheduler
# (schedule:work) — mỗi container có filesystem riêng nên cache độc lập,
# không đua ghi bootstrap/cache.
# ═══════════════════════════════════════════════════════════════════════════
set -e
cd /var/www/html

# ── Chọn pool php-fpm ──────────────────────────────────────────────────────
# Mặc định: pool storefront (docker/php/www.pool.conf đã bake thành zz-www.conf).
# FPM_POOL_PROFILE=admin → dùng pool CMS (ít children, timeout 300s) cho
# container infun-admin-php. Cùng image, khác env — xem docker/php/www.admin.conf.
# Chạy trước mọi lệnh artisan để nếu tên profile sai thì fail sớm, rõ ràng.
if [ -n "${FPM_POOL_PROFILE:-}" ]; then
    _pool="/usr/local/etc/php-fpm.d/available/www.${FPM_POOL_PROFILE}.conf"
    if [ -f "$_pool" ]; then
        echo "[entrypoint] FPM_POOL_PROFILE=${FPM_POOL_PROFILE} → nạp $_pool"
        cp "$_pool" /usr/local/etc/php-fpm.d/zz-www.conf
    else
        echo "[entrypoint] LỖI: FPM_POOL_PROFILE=${FPM_POOL_PROFILE} nhưng không có $_pool" >&2
        exit 1
    fi
fi

# Đảm bảo thư mục ghi được (volume storage có thể mount đè)
mkdir -p storage/framework/cache storage/framework/sessions \
         storage/framework/views storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

# Build lại package manifest theo ĐÚNG vendor (--no-dev) trong image này —
# bootstrap/cache/packages.php/services.php không được COPY từ dev (.dockerignore),
# nên thiếu bước này Laravel phải tự dò vendor/composer/installed.json mỗi
# request (chậm hơn, không đúng tinh thần image bất biến + cache sẵn).
php artisan package:discover --ansi

# Xoá cache cũ (KHÔNG chạm DB — an toàn chạy trước migrate)
php artisan optimize:clear >/dev/null 2>&1 || true

# Migrate PHẢI chạy TRƯỚC config:cache/route:cache/... — AppServiceProvider::
# registerObservers() khởi tạo model ngay lúc boot, trait HasSchemaCache query
# DESCRIBE bảng để tự suy $fillable (xem CLAUDE.md). Tức MỌI artisan command
# boot framework (kể cả config:cache) đều cần DB đã có schema từ trước; đặt
# migrate sau config:cache thì DB rỗng (lần dựng đầu) sẽ crash-loop vĩnh viễn
# dù đã bật RUN_MIGRATIONS=1, vì entrypoint chết trước khi kịp chạy migrate.
# --isolated: script này dùng chung cho php-fpm/queue/scheduler (có thể nhiều
# instance) — tránh N container cùng chạy migrate đồng thời khi start cùng lúc.
if [ "${RUN_MIGRATIONS:-0}" = "1" ]; then
    echo "[entrypoint] RUN_MIGRATIONS=1 → php artisan migrate --force"
    php artisan migrate --force --isolated || true
fi

php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache || true

# Reindex Meilisearch — OPT-IN qua RUN_SCOUT_IMPORT=1 (nặng, chạy tay tốt hơn).
if [ "${RUN_SCOUT_IMPORT:-0}" = "1" ]; then
    echo "[entrypoint] RUN_SCOUT_IMPORT=1 → scout:import Product"
    php artisan scout:import "App\\Models\\Entities\\Product" || true
fi

exec "$@"
