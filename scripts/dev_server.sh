#!/bin/bash
service mariadb start 2>/dev/null || mysqld_safe > /var/log/mariadb_safe.log 2>&1 &
for i in $(seq 1 30); do mysqladmin -uroot ping >/dev/null 2>&1 && break; sleep 1; done
export NABHRANG_DB_HOST=127.0.0.1
export NABHRANG_DB_PORT=3306
export NABHRANG_DB_NAME=nabhrang
export NABHRANG_DB_USER=nabhrang
export NABHRANG_DB_PASS=nabhrang_pass_2026
export NABHRANG_BASE_URL=https://nabhrang-admin.preview.emergentagent.com
export NABHRANG_ENV=development
exec php -S 0.0.0.0:3000 -t /app
