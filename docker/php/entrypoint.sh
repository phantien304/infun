#!/bin/sh
# Đảm bảo Laravel ghi được vào storage/ và bootstrap/cache/ khi mount từ host.
# Trên Windows volume mount đôi khi giữ quyền 0755 không cho www-data ghi.
set -e

cd /var/www/html

# Tạo thư mục nếu chưa có (lần đầu clone về có thể thiếu)
mkdir -p storage/framework/{cache,sessions,views,testing} \
         storage/logs \
         bootstrap/cache

# Mở quyền cho user www-data (FPM chạy bằng user này)
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true
chmod -R ug+rwX storage bootstrap/cache 2>/dev/null || true

# Nếu chưa cài vendor (lần đầu chạy) → cài luôn cho tiện
if [ ! -d vendor ] || [ ! -f vendor/autoload.php ]; then
    echo "[entrypoint] vendor/ chưa có, chạy composer install..."
    composer install --no-interaction --prefer-dist
fi

# Sinh APP_KEY nếu .env chưa có
if [ -f .env ] && ! grep -q "^APP_KEY=base64:" .env; then
    echo "[entrypoint] APP_KEY trống, sinh key..."
    php artisan key:generate --no-interaction || true
fi

exec "$@"
