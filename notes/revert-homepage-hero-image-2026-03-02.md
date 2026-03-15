# Revert note: Homepage hero right-image blend (2026-03-02)

## Backup file
- `/home/leonard/projects/wordpress/backups/homepage-690-pre-hero-image-2026-03-02.txt`

## Restore command
```bash
docker exec -i wordpress-db-1 mysql -uroot -proot_local_dev -D wordpress -e "UPDATE wp_posts SET post_content = REPLACE(post_content, post_content, '$(sed "s/'/''/g" /home/leonard/projects/wordpress/backups/homepage-690-pre-hero-image-2026-03-02.txt)') WHERE ID=690;"
```

## Safe restore (recommended)
If the one-liner has shell escaping issues, restore through phpMyAdmin:
1. Open http://localhost:8081
2. Database: `wordpress` -> table: `wp_posts` -> row `ID=690`
3. Replace `post_content` with contents of backup file above
4. Save
