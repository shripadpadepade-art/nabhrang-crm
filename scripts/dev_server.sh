#!/bin/bash
# Self-healing runtime for Nabhrang PHP app (survives pod restarts).
DATADIR=/app/storage/mysql
LOG=/app/storage/runtime.log

command -v php >/dev/null 2>&1 || { apt-get update -qq >> $LOG 2>&1; apt-get install -y -qq php-cli php-mysql >> $LOG 2>&1; }
command -v mariadbd >/dev/null 2>&1 || apt-get install -y -qq mariadb-server >> $LOG 2>&1

id mysql >/dev/null 2>&1 || useradd -r -s /bin/false mysql
mkdir -p /run/mysqld && chown mysql:mysql /run/mysqld

if [ ! -d "$DATADIR/mysql" ]; then
    mkdir -p "$DATADIR"
    chown -R mysql:mysql "$DATADIR"
    mariadb-install-db --user=mysql --datadir="$DATADIR" >> $LOG 2>&1
fi
chown -R mysql:mysql "$DATADIR"

if ! mysqladmin ping >/dev/null 2>&1; then
    /usr/sbin/mariadbd --user=mysql --datadir="$DATADIR" >> $LOG 2>&1 &
fi
for i in $(seq 1 60); do mysqladmin ping >/dev/null 2>&1 && break; sleep 1; done

if ! mysql -e "USE nabhrang" 2>/dev/null; then
    mysql -e "CREATE DATABASE nabhrang CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
    mysql nabhrang < /app/database.sql >> $LOG 2>&1
fi
mysql < /app/scripts/seed_runtime.sql >> $LOG 2>&1

export NABHRANG_DB_HOST=127.0.0.1
export NABHRANG_DB_PORT=3306
export NABHRANG_DB_NAME=nabhrang
export NABHRANG_DB_USER=nabhrang
export NABHRANG_DB_PASS=nabhrang_pass_2026
export NABHRANG_BASE_URL=https://nabhrang-admin.preview.emergentagent.com
export NABHRANG_ENV=development
exec php -S 0.0.0.0:3000 -t /app
