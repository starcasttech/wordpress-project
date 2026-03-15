#!/usr/bin/env bash
set -euo pipefail

BACKUP_FILE="/home/leonard/projects/wordpress/backups/homepage-690-pre-hero-image-2026-03-02.txt"
if [[ ! -f "$BACKUP_FILE" ]]; then
  echo "Backup file not found: $BACKUP_FILE" >&2
  exit 1
fi

ESCAPED_CONTENT=$(sed "s/'/''/g" "$BACKUP_FILE")

docker exec -i wordpress-db-1 mysql -uroot -proot_local_dev -D wordpress -e "UPDATE wp_posts SET post_content='${ESCAPED_CONTENT}' WHERE ID=690;"
echo "Homepage content for page ID 690 restored from backup."
