# PHP-FPM 8.2 cho dự án infun (Laravel 12) — dev mode.
# Code mount từ host (./:/var/www/html) nên KHÔNG COPY source ở đây.
# Image chỉ cài sẵn extension + composer để FPM chạy được app khi mount vào.

FROM php:8.2-fpm-alpine

# System deps + build deps cho các extension cần biên dịch (gd, intl, zip, redis).
# Tách phần build deps để xoá sau khi `pecl install` xong, image nhẹ hơn.
RUN apk add --no-cache \
        bash git curl icu-libs libpng libjpeg-turbo freetype libzip oniguruma \
        mariadb-client \
    && apk add --no-cache --virtual .build-deps \
        autoconf g++ make linux-headers \
        icu-dev libpng-dev libjpeg-turbo-dev freetype-dev libzip-dev oniguruma-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql mysqli mbstring bcmath intl zip gd exif pcntl \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps \
    && rm -rf /tmp/* /var/cache/apk/*

# Composer (bản chính thức), copy binary từ image official.
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# php.ini dev: tăng upload limit + memory cho artisan migrate / import + seed lớn.
# memory_limit=1024M: cần cho seed 2M review (PDO prepared statement + bulk insert
# array buffer dễ chạm 800MB ở chunk=500). 512M OOM ngay quanh 5-10% tiến độ.
RUN { \
        echo "memory_limit=1024M"; \
        echo "upload_max_filesize=64M"; \
        echo "post_max_size=64M"; \
        echo "max_execution_time=0"; \
        echo "date.timezone=Asia/Ho_Chi_Minh"; \
        echo "realpath_cache_size=4096k"; \
        echo "realpath_cache_ttl=600"; \
        echo ""; \
        echo "[opcache]"; \
        echo "opcache.enable=1"; \
        echo "opcache.enable_cli=0"; \
        echo "opcache.memory_consumption=256"; \
        echo "opcache.interned_strings_buffer=16"; \
        echo "opcache.max_accelerated_files=20000"; \
        echo "opcache.validate_timestamps=1"; \
        echo "opcache.revalidate_freq=2"; \
        echo "opcache.fast_shutdown=1"; \
        echo "opcache.jit_buffer_size=128M"; \
        echo "opcache.jit=tracing"; \
    } > /usr/local/etc/php/conf.d/zz-app.ini

# User www-data đã có sẵn trong image (uid 82). Đảm bảo writable storage/cache
# khi mount từ Windows — entrypoint chỉnh quyền lúc start.
WORKDIR /var/www/html

# Entrypoint: chmod storage/bootstrap rồi exec php-fpm.
COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 9000
ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]
