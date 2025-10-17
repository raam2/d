#!/bin/bash
# MariaDB Upgrade Playbook
# Works on Debian/Ubuntu inside your LXC container

set -euo pipefail

TARGET_VERSION="11.4"   # Change if you want a different MariaDB release
BACKUP_FILE="/var/www/html/bharat_accounting/all_db-dump/mariadb_full_backup_$(date +%F).sql"

echo "=== Step 1: Taking full backup to $BACKUP_FILE ==="
mysqldump --all-databases --routines --events --triggers \
  --single-transaction --quick --skip-comments --skip-dump-date \
  > "$BACKUP_FILE"

echo "✅ Backup complete."

echo "=== Step 2: Stopping MariaDB service ==="
systemctl stop mariadb

echo "=== Step 3: Adding MariaDB $TARGET_VERSION repository ==="
apt-get install -y curl software-properties-common
curl -LsS https://r.mariadb.com/downloads/mariadb_repo_setup \
  | bash -s -- --mariadb-server-version="$TARGET_VERSION"

echo "=== Step 4: Upgrading MariaDB packages ==="
apt-get update
DEBIAN_FRONTEND=noninteractive apt-get install -y mariadb-server

echo "=== Step 5: Starting MariaDB service ==="
systemctl start mariadb

echo "=== Step 6: Running mariadb-upgrade ==="
mariadb-upgrade

echo "=== Step 7: Verifying version ==="
mysql --version

echo "✅ Upgrade complete. Backup is at $BACKUP_FILE"

