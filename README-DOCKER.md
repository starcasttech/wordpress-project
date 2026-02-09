# WordPress Docker Setup

This WordPress installation is configured to run in Docker containers for local development and testing.

## Prerequisites

- Docker Engine 20.10+
- Docker Compose 2.0+

## Quick Start

1. **Start the containers:**
   ```bash
   docker-compose up -d
   ```

2. **Access your WordPress site:**
   - WordPress: http://localhost:8080
   - phpMyAdmin: http://localhost:8081

3. **Default Credentials:**
   - Database Name: `wordpress`
   - Database User: `wpuser`
   - Database Password: `wppassword`
   - Database Root Password: `rootpassword`

## Container Services

### WordPress Application
- **Container:** `wordpress_app`
- **Port:** 8080 (configurable via `.env`)
- **Image:** Custom build from `Dockerfile`
- **Features:**
  - PHP 8.2 with Apache
  - All WordPress files
  - Auto-reload on file changes

### MySQL Database
- **Container:** `wordpress_db`
- **Internal Port:** 3306
- **Image:** mysql:8.0
- **Features:**
  - Persistent data volume
  - Auto-import SQL dump on first run
  - Health checks

### phpMyAdmin
- **Container:** `wordpress_phpmyadmin`
- **Port:** 8081 (configurable via `.env`)
- **Image:** phpmyadmin/phpmyadmin:latest
- **Features:**
  - Web-based database management
  - Import/export capabilities
  - 100MB upload limit

## Common Commands

### Start containers (detached mode)
```bash
docker-compose up -d
```

### Start containers (with logs)
```bash
docker-compose up
```

### Stop containers
```bash
docker-compose down
```

### Stop containers and remove volumes (CAUTION: deletes database)
```bash
docker-compose down -v
```

### View logs
```bash
# All containers
docker-compose logs -f

# Specific container
docker-compose logs -f wordpress
docker-compose logs -f db
```

### Restart containers
```bash
docker-compose restart
```

### Rebuild containers (after Dockerfile changes)
```bash
docker-compose up -d --build
```

### Execute commands in WordPress container
```bash
# Access bash shell
docker-compose exec wordpress bash

# Run WP-CLI commands
docker-compose exec wordpress wp --version
```

### Database Management

#### Import SQL dump manually
```bash
# Copy SQL file into container
docker cp your-dump.sql wordpress_db:/tmp/dump.sql

# Import into database
docker-compose exec db mysql -u wpuser -pwppassword wordpress < /tmp/dump.sql
```

#### Export database
```bash
docker-compose exec db mysqldump -u wpuser -pwppassword wordpress > backup-$(date +%Y%m%d).sql
```

#### Access MySQL CLI
```bash
docker-compose exec db mysql -u wpuser -pwppassword wordpress
```

## Configuration

### Environment Variables

Edit the `.env` file to customize settings:

```env
# Database
MYSQL_DATABASE=wordpress
MYSQL_USER=wpuser
MYSQL_PASSWORD=wppassword
MYSQL_ROOT_PASSWORD=rootpassword

# WordPress
WORDPRESS_PORT=8080
WORDPRESS_DEBUG=true

# phpMyAdmin
PHPMYADMIN_PORT=8081
```

### Initial Database Import

The SQL dump `starcast-deploy.sql` will be automatically imported on the first container startup. If you need to reimport:

1. Stop and remove volumes:
   ```bash
   docker-compose down -v
   ```

2. Start fresh:
   ```bash
   docker-compose up -d
   ```

### URL Configuration

If you need to change the WordPress URL after import, you can use WP-CLI:

```bash
docker-compose exec wordpress wp search-replace 'http://old-url.com' 'http://localhost:8080' --all-tables
```

## Troubleshooting

### Port conflicts
If ports 8080 or 8081 are already in use, change them in `.env`:
```env
WORDPRESS_PORT=8090
PHPMYADMIN_PORT=8091
```

### Permission issues
If you encounter permission errors:
```bash
docker-compose exec wordpress chown -R www-data:www-data /var/www/html
```

### Database connection errors
1. Check if database is healthy:
   ```bash
   docker-compose ps
   ```

2. View database logs:
   ```bash
   docker-compose logs db
   ```

### Clear WordPress cache
```bash
docker-compose exec wordpress wp cache flush
```

### Reset everything
```bash
# Stop containers and remove all data
docker-compose down -v

# Remove built images
docker-compose down --rmi all

# Start fresh
docker-compose up -d --build
```

## Development Workflow

1. **Making code changes:**
   - Edit files in `wp-content/` directory
   - Changes are reflected immediately (no rebuild needed)

2. **Modifying Dockerfile:**
   - After changing Dockerfile, rebuild:
     ```bash
     docker-compose up -d --build
     ```

3. **Database changes:**
   - Use phpMyAdmin (http://localhost:8081)
   - Or use MySQL CLI via docker-compose exec

4. **Installing plugins/themes:**
   - Use WordPress admin panel
   - Or add files directly to `wp-content/plugins/` or `wp-content/themes/`

## Production Deployment

This setup is for **local development only**. For production:
- Use proper secrets management (not `.env` files)
- Enable SSL/TLS
- Use production-grade web server (Nginx)
- Implement proper backup strategies
- Configure security headers
- Use CDN for static assets

## Backup Strategy

### Backup database
```bash
docker-compose exec db mysqldump -u wpuser -pwppassword wordpress > backup-$(date +%Y%m%d-%H%M%S).sql
```

### Backup wp-content
```bash
tar -czf wp-content-backup-$(date +%Y%m%d-%H%M%S).tar.gz wp-content/
```

## Additional Resources

- [Docker Documentation](https://docs.docker.com/)
- [Docker Compose Documentation](https://docs.docker.com/compose/)
- [WordPress Docker Image](https://hub.docker.com/_/wordpress)
- [MySQL Docker Image](https://hub.docker.com/_/mysql)
