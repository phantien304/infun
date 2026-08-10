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

# bootstrap/cache/config.php (từ `artisan config:cache`/`optimize`) bake cứng
# giá trị .env tại THỜI ĐIỂM chạy lệnh — nếu lệnh đó chạy bằng PHP ngoài
# Docker (Herd/XAMPP/Windows) thì nó bake REDIS_HOST/DB_HOST=127.0.0.1 thay vì
# tên service (redis/mysql). Vì code bind-mount dùng chung giữa host và
# infun-php/infun-queue, file cache sai đó đè luôn env đúng compose truyền
# vào container → Redis/DB connection refused, container crash-loop liên tục
# (xem incident 2026-08-06, vmmem 40% CPU cả tháng vì lỗi y hệt).
# Dev không cần config cache (không có lợi ích hiệu năng đáng kể ở đây, code
# đã sẵn trên đĩa local) nên xoá nó mỗi lần container khởi động — kể cả khi
# ai đó lỡ chạy optimize sai chỗ, lần restart kế tiếp (crash-loop tự kích
# entrypoint lại) sẽ tự dọn và phục hồi thay vì kẹt vĩnh viễn.
rm -f bootstrap/cache/config.php bootstrap/cache/routes-v7.php

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
