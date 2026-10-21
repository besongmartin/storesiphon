#!/usr/bin/env bash
# Nightly MariaDB backup, compressed and encrypted, pushed to S3-compatible storage (EU region).
# Needs: mariadb-dump, gzip, age (https://age-encryption.org) and rclone configured with remote "backups".
set -euo pipefail
source /var/www/storesiphon/shared/.env.backup   # DB_DATABASE, DB_USERNAME, DB_PASSWORD, AGE_RECIPIENT

STAMP=$(date +%Y%m%d-%H%M%S)
FILE="/tmp/storesiphon-$STAMP.sql.gz.age"

mariadb-dump --single-transaction --quick --routines -u"$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" \
  | gzip -9 | age -r "$AGE_RECIPIENT" > "$FILE"
rclone copy "$FILE" backups:storesiphon-db/
rm -f "$FILE"

# Keep 30 days of backups
rclone delete --min-age 30d backups:storesiphon-db/
echo "$(date -Is) backup ok: $STAMP"
