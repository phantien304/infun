# deploy/ — Bộ config PROD cho mốc 30k user

> Đối chiếu checklist: `docs/SCALE-30K.md` (mục B, ưu tiên 1-4).
> Bản mô phỏng chạy được ngay trên máy dev: `docker-compose.scale.yml`.

```
deploy/
  redis/redis-session.conf   # instance session+queue (noeviction, AOF)
  redis/redis-cache.conf     # instance cache (allkeys-lru, không persistence)
  mysql/master.cnf           # binlog + GTID
  mysql/replica.cnf          # read_only, server-id riêng từng máy
  proxysql/proxysql.cnf      # pool + route r/w theo port + lag-aware (sidecar mỗi app server)
  php/www.conf               # php-fpm pool prod (pm=static + công thức size)
  php/opcache-prod.ini       # validate_timestamps=0, JIT
  systemd/infun-queue.service      # queue worker (chạy nhiều instance được)
  systemd/infun-scheduler.service  # schedule:work (CHỈ 1 instance)
  env.production.example     # các key .env khác biệt so với dev
```

## Thứ tự triển khai (ít rủi ro → nhiều)

### 1. Redis tách instance + phpredis

- Cài 2 instance (2 process cùng máy port khác nhau, hoặc 2 máy/managed):
  - **session+queue** → `redis/redis-session.conf` (port 6379). TUYỆT ĐỐI
    không dùng eviction — mất key = đăng xuất hàng loạt / mất job.
  - **cache** → `redis/redis-cache.conf` (port 6380). Evict thoải mái.
- `.env`: `REDIS_HOST` trỏ instance session, `REDIS_CACHE_HOST/PORT` trỏ
  instance cache, `REDIS_CLIENT=phpredis` (cài `pecl install redis`).
- HA về sau: Sentinel / managed (ElastiCache) — chưa cần ngay.

### 2. MySQL read/write split (1 master + 2 replica)

Trên **master**: chép `mysql/master.cnf` vào `/etc/mysql/conf.d/`, restart, rồi:

```sql
CREATE USER 'repl'@'%' IDENTIFIED BY '<mật khẩu mạnh>';
GRANT REPLICATION SLAVE ON *.* TO 'repl'@'%';
```

Trên **mỗi replica**: chép `mysql/replica.cnf` (SỬA `server-id` = 2, 3…),
restart, seed data rồi bám GTID:

```bash
# seed từ master (không lock nhờ --single-transaction)
mariadb-dump -h <master> -uroot -p --single-transaction --master-data=1 --gtid \
  --routines --events --triggers --databases infun | mariadb -uroot -p
```

```sql
CHANGE MASTER TO MASTER_HOST='<master>', MASTER_USER='repl',
  MASTER_PASSWORD='...', MASTER_USE_GTID=slave_pos;
START SLAVE;
SHOW SLAVE STATUS\G  -- Slave_IO_Running=Yes, Slave_SQL_Running=Yes
```

`.env` app server: điền `DB_WRITE_HOST` / `DB_READ_HOST1` / `DB_READ_HOST2`
rồi `php artisan config:clear && php artisan config:cache`.

**Monitor bắt buộc:** `Seconds_Behind_Master` (MariaDB) — lag > vài giây thì
điều tra ngay. Nhớ: replica chỉ tăng khả năng ĐỌC; flash-sale (ghi) vẫn dồn
master.

### 2b. ProxySQL (pool + route + lag-aware)

Chạy **sidecar trên mỗi app server** (app nối `127.0.0.1:6033/6034`) — khỏi
cần HA riêng:

```bash
apt install proxysql   # hoặc docker run -v proxysql.cnf:/etc/proxysql.cnf proxysql/proxysql:2.6.6
cp proxysql/proxysql.cnf /etc/proxysql.cnf   # SỬA IP + 3 password trước
systemctl enable --now proxysql
```

Tạo monitor user trên master (lan xuống replica qua replication):

```sql
CREATE USER 'monitor'@'%' IDENTIFIED BY '<PASS_MONITOR>';
GRANT REPLICATION CLIENT, SLAVE MONITOR ON *.* TO 'monitor'@'%';
```

`.env`: `DB_WRITE_HOST=127.0.0.1` + `DB_WRITE_PORT=6033`,
`DB_READ_HOST1=127.0.0.1` + `DB_READ_PORT=6034` (bỏ `DB_READ_HOST2` —
ProxySQL tự cân giữa các replica và tự SHUN replica lag >10s).

Được thêm: failover — promote replica (`SET GLOBAL read_only=0`) là ProxySQL
tự trỏ write sang node mới trong ~2s, app không đổi config.

Kiểm tra: `mysql -h127.0.0.1 -P6032 -uadmin -p` →
`SELECT * FROM stats_mysql_connection_pool;` (xem pool/hostgroup) và
`SELECT * FROM mysql_server_replication_lag_log ORDER BY time_start_us DESC LIMIT 10;`

### 3. App server + php-fpm

- Mỗi app server: chép `php/www.conf` (SỬA `pm.max_children` theo RAM — công
  thức trong file) + `php/opcache-prod.ini` vào conf.d.
- Deploy ritual: `composer install --no-dev -o && php artisan config:cache
  && php artisan route:cache && php artisan view:cache` rồi
  `systemctl reload php8.2-fpm` (reload = restart worker → nạp opcache mới).
- Nhiều app server stateless sau LB — mẫu nginx LB có sẵn:
  `docker/nginx/default.lb.conf` (least_conn + healthcheck `/__lb_health`).

### 4. Queue worker + Scheduler

```bash
cp systemd/infun-queue.service systemd/infun-scheduler.service /etc/systemd/system/
systemctl daemon-reload
systemctl enable --now infun-queue infun-scheduler
```

- Queue có thể chạy nhiều instance (`systemctl start infun-queue@1 2 3` nếu
  chuyển sang template unit) hoặc nâng cấp Horizon (cần `composer require
  laravel/horizon`).
- **Scheduler chỉ 1 instance duy nhất toàn hệ thống** — chạy trùng là
  `stock:release-expired` / seed job chạy đôi. Không dùng systemd thì cron:

```cron
* * * * * cd /var/www/infun && php artisan schedule:run >> /dev/null 2>&1
```

## Verify sau khi lên

```sql
-- Oversell check (chạy trên master sau load test):
SELECT ps.id, ps.on_hand, ps.reserved FROM product_stock ps
WHERE ps.reserved > ps.on_hand OR ps.on_hand < 0;  -- phải rỗng
```

```bash
php artisan config:show database.connections.mysql.read   # thấy 2 replica
redis-cli -p 6379 CONFIG GET maxmemory-policy             # noeviction
redis-cli -p 6380 CONFIG GET maxmemory-policy             # allkeys-lru
php -r "var_dump(extension_loaded('redis'));"             # true = phpredis
systemctl status infun-queue infun-scheduler
```
