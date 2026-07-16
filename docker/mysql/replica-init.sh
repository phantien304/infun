#!/bin/bash
# ─────────────────────────────────────────────────────────────────────────────
# Khởi tạo REPLICA MariaDB — chạy đúng 1 LẦN khi volume còn trống
# (mount vào /docker-entrypoint-initdb.d/, entrypoint mariadb tự gọi).
#
# Các bước:
#   1. Chờ master sẵn sàng
#   2. Tạo replication user trên master (idempotent — replica2 chạy lại vô hại)
#   3. mariadb-dump từ master (--single-transaction: không lock, --gtid: kèm
#      vị trí GTID để replica biết bám từ đâu)
#   4. Import vào replica local
#   5. CHANGE MASTER TO ... MASTER_USE_GTID=slave_pos + START SLAVE
#
# Env (set từ docker-compose.scale.yml):
#   MASTER_HOST, MASTER_ROOT_PASSWORD, MASTER_DB, REPL_USER, REPL_PASSWORD
# ─────────────────────────────────────────────────────────────────────────────
set -euo pipefail

MASTER_HOST="${MASTER_HOST:-mysql}"
MASTER_ROOT_PASSWORD="${MASTER_ROOT_PASSWORD:-root}"
MASTER_DB="${MASTER_DB:-infun}"
REPL_USER="${REPL_USER:-repl}"
REPL_PASSWORD="${REPL_PASSWORD:-repl_secret}"

echo "[replica-init] chờ master ${MASTER_HOST} sẵn sàng..."
until mariadb -h"$MASTER_HOST" -uroot -p"$MASTER_ROOT_PASSWORD" -e "SELECT 1" >/dev/null 2>&1; do
    sleep 2
done

echo "[replica-init] tạo replication user + monitor user trên master (idempotent)..."
# monitor: ProxySQL dùng để check @@read_only + Seconds_Behind_Master.
# MariaDB 10.5+: SHOW SLAVE STATUS cần quyền SLAVE MONITOR (REPLICATION CLIENT
# chỉ đủ cho binlog status).
mariadb -h"$MASTER_HOST" -uroot -p"$MASTER_ROOT_PASSWORD" <<SQL
CREATE USER IF NOT EXISTS '$REPL_USER'@'%' IDENTIFIED BY '$REPL_PASSWORD';
GRANT REPLICATION SLAVE ON *.* TO '$REPL_USER'@'%';
CREATE USER IF NOT EXISTS 'monitor'@'%' IDENTIFIED BY 'monitor_pass';
GRANT REPLICATION CLIENT, SLAVE MONITOR ON *.* TO 'monitor'@'%';
FLUSH PRIVILEGES;
SQL

echo "[replica-init] dump ${MASTER_DB} từ master (single-transaction + gtid)..."
mariadb-dump -h"$MASTER_HOST" -uroot -p"$MASTER_ROOT_PASSWORD" \
    --single-transaction --master-data=1 --gtid \
    --routines --events --triggers \
    --databases "$MASTER_DB" > /tmp/master-seed.sql

echo "[replica-init] import dump vào replica (DB lớn sẽ mất vài phút)..."
# Import qua socket local (server đang ở giai đoạn init, chưa mở network).
# Dump có sẵn `SET GLOBAL gtid_slave_pos=...` (nhờ --master-data=1 --gtid)
# → sau import replica đã biết đúng vị trí GTID để bám.
mariadb -uroot -p"$MARIADB_ROOT_PASSWORD" < /tmp/master-seed.sql
rm -f /tmp/master-seed.sql

# Monitor user phải tồn tại TRÊN CHÍNH replica này (dump chỉ chứa DB app,
# user nằm ở mysql db không được dump; CREATE USER trên master xảy ra trước
# vị trí GTID của dump nên cũng không replay qua replication).
echo "[replica-init] tạo monitor user local trên replica..."
mariadb -uroot -p"$MARIADB_ROOT_PASSWORD" <<SQL
CREATE USER IF NOT EXISTS 'monitor'@'%' IDENTIFIED BY 'monitor_pass';
GRANT REPLICATION CLIENT, SLAVE MONITOR ON *.* TO 'monitor'@'%';
FLUSH PRIVILEGES;
SQL

echo "[replica-init] cấu hình replication (GTID slave_pos) + START SLAVE..."
mariadb -uroot -p"$MARIADB_ROOT_PASSWORD" <<SQL
CHANGE MASTER TO
    MASTER_HOST='$MASTER_HOST',
    MASTER_PORT=3306,
    MASTER_USER='$REPL_USER',
    MASTER_PASSWORD='$REPL_PASSWORD',
    MASTER_USE_GTID=slave_pos,
    MASTER_CONNECT_RETRY=5;
START SLAVE;
SQL

sleep 3
echo "[replica-init] trạng thái replication:"
mariadb -uroot -p"$MARIADB_ROOT_PASSWORD" -e "SHOW SLAVE STATUS\G" \
    | grep -E "Slave_IO_Running|Slave_SQL_Running|Seconds_Behind_Master|Last_Error" || true
echo "[replica-init] xong. Verify sau khi container healthy:"
echo "  docker compose exec <replica> mariadb -uroot -proot -e 'SHOW SLAVE STATUS\\G'"
