# Starcast Technologies WordPress Site

WordPress-based ISP website for Starcast Technologies, deployed via GitHub to Railway.

## 🚀 Quick Start

### Local Development (Docker)
```bash
# Start all services
sudo docker compose up -d

# Access site
http://localhost:8090

# Access phpMyAdmin
http://localhost:8081
```

### Before Every Deployment
```bash
# CRITICAL: Run security check before pushing to GitHub
./verify-gitignore.sh
```

## 📋 Documentation

- **[CLAUDE.md](./CLAUDE.md)** - Complete project guide, deployment instructions, security requirements
- **[README-DOCKER.md](./README-DOCKER.md)** - Docker environment setup and usage
- **[.env.example](./.env.example)** - Environment variables template

## 🔐 Security & Deployment

### NEVER Commit These Files:
- `.env` - Contains sensitive credentials
- `wp-config.php` - Database configuration
- `*.sql` - Database dumps with real data
- `docker-compose.yml` - Local dev only
- Build/API documentation with keys

### Pre-Deployment Checklist:
1. ✅ Test locally at http://localhost:8090
2. ✅ Run `./verify-gitignore.sh`
3. ✅ Verify no sensitive files in `git status`
4. ✅ Push to GitHub `main` branch
5. ✅ Railway auto-deploys and runs

## 🌐 Deployment Workflow

```bash
# 1. Verify gitignore
./verify-gitignore.sh

# 2. Commit changes
git add .
git commit -m "Your change description"

# 3. Push to GitHub (triggers Railway deployment)
git push origin main
```

## 🛠 Technology Stack

- **CMS:** WordPress 6.4+ (PHP 8.2 + Apache)
- **Database:** MySQL 8.0
- **Theme:** Kadence + Custom starcast-kadence-child theme
- **Local Dev:** Docker + Docker Compose
- **Hosting:** Railway
- **Version Control:** GitHub

## 📦 Custom Theme

- **starcast-kadence-child**: Custom child theme based on Kadence
- Located in: `wp-content/themes/starcast-kadence-child/`

## 🔗 Access Points

### Local Development
- **WordPress:** http://localhost:8090
- **phpMyAdmin:** http://localhost:8081
- **Database:** localhost:3306 (from host)

### Production
- **Site URL:** [Set by Railway after deployment]
- **Admin Panel:** [Production URL]/wp-admin

## ⚙️ Environment Variables

Local development uses `.env` file (gitignored). For Railway production, set these in the Railway dashboard:

```env
# Database (auto-configured by Railway MySQL plugin)
WORDPRESS_DB_HOST=<railway-mysql>
WORDPRESS_DB_NAME=<database>
WORDPRESS_DB_USER=<user>
WORDPRESS_DB_PASSWORD=<password>

# WordPress
WORDPRESS_DEBUG=false
WP_ENVIRONMENT_TYPE=production

# API Keys (if needed)
STARCAST_GOOGLE_MAPS_API_KEY=<set-in-railway>
```

See `.env.example` for complete list.

## 🐳 Docker Commands

```bash
# Start containers
sudo docker compose up -d

# Stop containers
sudo docker compose down

# View logs
sudo docker compose logs -f wordpress

# Restart WordPress
sudo docker compose restart wordpress

# Database backup
sudo docker compose exec db mysqldump -uwpuser -pwppassword wordpress > backup.sql
```

## 🔧 Requirements

### Local Development
- Docker Engine 20.10+
- Docker Compose 2.0+
- Git

### Production (Railway)
- PHP 8.2+
- MySQL 8.0+
- WordPress 6.4+

## 📚 Additional Resources

- **Full Documentation:** See [CLAUDE.md](./CLAUDE.md)
- **Railway Docs:** https://docs.railway.app/
- **WordPress Docs:** https://wordpress.org/documentation/

## ⚠️ Important Notes

- Port 8090 used to avoid conflicts with Next.js (port 8080)
- Railway auto-deploys on push to `main` branch
- All secrets must be set in Railway dashboard
- Run `verify-gitignore.sh` before every deployment
- Keep wp-content/uploads in git but exclude cache directories

---

**Project:** Starcast Technologies ISP Site
**Deployment:** Railway (via GitHub)
**Local Port:** 8090
**Last Updated:** 2026-02-08
