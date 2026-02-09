# WordPress ISP Site - Project Documentation

## Project Overview

This is a WordPress ISP (Internet Service Provider) site that will be deployed to **Railway** via **GitHub**. The project uses Docker for local development and is configured for production deployment through Railway's GitHub integration.

## Technology Stack

- **CMS:** WordPress (PHP 8.2 + Apache)
- **Database:** MySQL 8.0
- **Local Development:** Docker + Docker Compose
- **Version Control:** Git + GitHub
- **Deployment:** Railway (automated from GitHub)
- **Local Port:** 8090 (WordPress), 8081 (phpMyAdmin)

## Important Deployment Rules

### 🚨 CRITICAL SECURITY REQUIREMENTS

1. **NO SENSITIVE DATA IN GIT**
   - API keys, credentials, and tokens MUST be gitignored
   - Build documentation is for DEVELOPMENT ONLY - never deploy
   - Internal reference documentation stays local

2. **PRE-DEPLOYMENT CHECKLIST**
   - ✅ Run git ignore verification before EVERY deployment
   - ✅ Verify no `.env` files are committed
   - ✅ Check no build/API documentation is included
   - ✅ Confirm no SQL dumps with real data are committed
   - ✅ Validate only site files are being pushed

3. **DEPLOYMENT WORKFLOW**
   ```bash
   # 1. Check what will be committed
   git status

   # 2. Verify gitignore is working
   git check-ignore -v .env wp-config.php *.sql

   # 3. List all files that will be pushed
   git ls-files

   # 4. If clean, proceed with deployment
   git add .
   git commit -m "Your message"
   git push origin main
   ```

## Repository Structure

```
wordpress/
├── wp-admin/              # WordPress admin (deploy)
├── wp-includes/           # WordPress core (deploy)
├── wp-content/            # Themes, plugins, uploads (deploy)
│   ├── themes/
│   ├── plugins/
│   └── uploads/
├── docker-compose.yml     # Local dev only (DO NOT DEPLOY)
├── Dockerfile            # Local dev only (DO NOT DEPLOY)
├── .env                  # NEVER COMMIT - gitignored
├── wp-config.php         # NEVER COMMIT - gitignored
├── .gitignore            # Always review before deployment
├── CLAUDE.md             # This file (safe to deploy)
└── README-DOCKER.md      # Local dev docs (optional deploy)
```

## What Gets Deployed vs What Stays Local

### ✅ DEPLOY TO RAILWAY (via GitHub)
- WordPress core files (wp-admin, wp-includes)
- wp-content directory (themes, plugins)
- Public assets (CSS, JS, images)
- .gitignore file
- Documentation without sensitive info

### ❌ NEVER DEPLOY (MUST BE GITIGNORED)
- `.env` - Environment variables with secrets
- `wp-config.php` - Database credentials
- `*.sql` - Database dumps with real data
- `docker-compose.yml` - Local dev configuration
- `Dockerfile` - Local dev configuration
- Build documentation with API keys
- Internal reference documents
- Development notes with credentials
- `node_modules/` if using any build tools
- `.DS_Store`, `.idea/`, `.vscode/` - IDE files
- `*.log` - Log files
- Backup files (`*.bak`, `*.old`)

## Local Development Setup

### Start Development Environment
```bash
# Start Docker containers
sudo docker compose up -d

# View logs
sudo docker compose logs -f

# Stop containers
sudo docker compose down
```

### Access Points
- WordPress: http://localhost:8090
- phpMyAdmin: http://localhost:8081
- Database: localhost:3306 (from host)

### Database Credentials (Local Only)
```
Database: wordpress
User: wpuser
Password: wppassword
Root Password: rootpassword
```

## Railway Deployment Configuration

### Environment Variables (Set in Railway Dashboard)

Railway will need these environment variables configured:

```
# Database (Railway provides these automatically with MySQL plugin)
WORDPRESS_DB_HOST=<railway-mysql-host>
WORDPRESS_DB_NAME=<database-name>
WORDPRESS_DB_USER=<database-user>
WORDPRESS_DB_PASSWORD=<database-password>

# WordPress Configuration
WORDPRESS_DEBUG=false
WP_ENVIRONMENT_TYPE=production

# API Keys (if needed)
STARCAST_GOOGLE_MAPS_API_KEY=<set-in-railway-dashboard>

# Security Keys (generate at https://api.wordpress.org/secret-key/1.1/salt/)
WORDPRESS_AUTH_KEY=<generate-new>
WORDPRESS_SECURE_AUTH_KEY=<generate-new>
WORDPRESS_LOGGED_IN_KEY=<generate-new>
WORDPRESS_NONCE_KEY=<generate-new>
WORDPRESS_AUTH_SALT=<generate-new>
WORDPRESS_SECURE_AUTH_SALT=<generate-new>
WORDPRESS_LOGGED_IN_SALT=<generate-new>
WORDPRESS_NONCE_SALT=<generate-new>
```

### Railway Setup Steps

1. **Connect GitHub Repository**
   - Link Railway to your GitHub account
   - Select this WordPress repository
   - Set deployment branch to `main`

2. **Add MySQL Plugin**
   - Add MySQL database service in Railway
   - Railway auto-configures connection variables

3. **Configure Build Settings**
   - Root directory: `/` (or path to WordPress)
   - No build command needed (PHP doesn't require compilation)
   - Start command: Use Railway's PHP server

4. **Set Environment Variables**
   - Copy all necessary env vars from local `.env`
   - Generate new security keys for production
   - Use Railway's MySQL connection variables

5. **Deploy**
   - Push to GitHub `main` branch
   - Railway auto-deploys on every push

## Build & Deployment Process

### Build Information

**WordPress Build Process:**
- WordPress is a PHP-based CMS that does NOT require compilation
- No build step needed - files are served directly
- Railway serves PHP files using Apache/Nginx + PHP-FPM

**What Gets Built/Deployed:**
```
✅ WordPress Core (wp-admin, wp-includes, wp-*.php)
✅ Custom Theme (wp-content/themes/starcast-kadence-child)
✅ Plugins (wp-content/plugins/)
✅ Uploaded Media (wp-content/uploads/)
✅ Configuration (handled via environment variables)
❌ Docker files (local dev only)
❌ Database dumps (local backups only)
❌ Build/API documentation (development reference only)
```

### Railway Build Configuration

**Railway Buildpack Detection:**
Railway automatically detects PHP applications and configures:
- PHP 8.2 runtime
- Apache or Nginx web server
- MySQL connection from Railway's MySQL plugin
- Environment variable injection

**No Custom Build Command Needed:**
```yaml
# Railway automatically handles:
# - PHP dependency installation (if composer.json exists)
# - Web server configuration
# - Database connection setup
```

### Build Verification Checklist

Run this BEFORE every push to GitHub:

```bash
# 1. Security Check
./verify-gitignore.sh

# 2. Verify files to be deployed
git status
git ls-files

# 3. Check for sensitive data
git diff --cached

# 4. Confirm build documentation excluded
git ls-files | grep -i "build\|api-key\|credential"
# Should return nothing

# 5. Test locally first
# Access http://localhost:8090 and verify site works
```

### Automated Build Process (Railway)

**On Every Git Push to `main`:**

1. **Trigger:** GitHub webhook notifies Railway of new commit
2. **Clone:** Railway clones repository from GitHub
3. **Detect:** Buildpack detects PHP application
4. **Configure:** Railway sets up PHP 8.2 + Apache environment
5. **Inject:** Environment variables added from Railway dashboard
6. **Deploy:** WordPress files served via web server
7. **Database:** MySQL connection established automatically
8. **Health Check:** Railway verifies deployment is responding
9. **Live:** New version is live (zero-downtime deployment)

### Build Exclusions (Critical)

**NEVER Deploy These:**

```
# Local Development Only
docker-compose.yml       # Docker orchestration (local only)
Dockerfile              # Container definition (local only)
.dockerignore           # Docker build exclusions
.env                    # Local environment variables
.env.local              # Local overrides

# Sensitive Data
wp-config.php           # Contains database credentials
*.sql                   # Database dumps with real data
*.sql.gz                # Compressed database backups

# Build/Development Documentation
docs/build/             # Build process documentation
docs/api/               # API documentation with keys
BUILD.md                # Internal build notes
API-KEYS.md             # API key reference
DEVELOPMENT-NOTES.md    # Development reference

# Temporary/Generated Files
*.log                   # Log files
*.bak                   # Backup files
*.tmp                   # Temporary files
tmp/                    # Temporary directory
```

### Build Documentation Management

**Development Documentation Structure:**
```
docs/
├── build/              # ❌ NEVER COMMIT - Build process with credentials
├── api/                # ❌ NEVER COMMIT - API docs with keys
├── internal/           # ❌ NEVER COMMIT - Internal reference
└── public/             # ✅ OK TO COMMIT - Public documentation
```

**How to Handle Build Documentation:**

1. **Store Locally Only:**
   ```bash
   # Create docs directory (gitignored)
   mkdir -p docs/{build,api,internal}

   # Add to .gitignore
   echo "docs/build/" >> .gitignore
   echo "docs/api/" >> .gitignore
   echo "docs/internal/" >> .gitignore
   ```

2. **Use Secure Storage for Team Sharing:**
   - Private company wiki
   - Encrypted cloud storage
   - Password-protected documentation platform
   - NEVER in public Git repository

3. **Reference in Code Comments (Without Secrets):**
   ```php
   // See internal build docs for API configuration
   // Reference: docs/api/google-maps-setup.md (local only)
   define('MAPS_API_KEY', getenv('STARCAST_GOOGLE_MAPS_API_KEY'));
   ```

### Pre-Deployment Build Check Script

**Enhanced verification including build docs:**

```bash
#!/bin/bash
# comprehensive-build-check.sh

echo "🏗️  Build & Deployment Verification"
echo "===================================="

# Check for build documentation
echo "📚 Checking for build documentation..."
if git ls-files | grep -E "(docs/build|docs/api|docs/internal|BUILD\.md|API-KEYS\.md)"; then
    echo "❌ ERROR: Build documentation found in git!"
    echo "These files should be in .gitignore"
    exit 1
else
    echo "✅ No build documentation in git"
fi

# Check for sensitive files
echo "🔐 Checking for sensitive files..."
./verify-gitignore.sh

# Verify WordPress core files present
echo "📦 Verifying WordPress files..."
if [ -f "wp-config-sample.php" ] && [ -d "wp-admin" ] && [ -d "wp-content" ]; then
    echo "✅ WordPress core files present"
else
    echo "❌ ERROR: WordPress core files missing"
    exit 1
fi

# Check file count
FILE_COUNT=$(git ls-files | wc -l)
echo "📊 Total files to deploy: $FILE_COUNT"

echo ""
echo "✅ Build verification complete!"
echo "Safe to push to GitHub for Railway deployment"
```

### Deployment Pipeline

```mermaid
Local Development
    ↓
Run verify-gitignore.sh
    ↓
Git commit to local
    ↓
Git push to GitHub main
    ↓
GitHub webhook to Railway
    ↓
Railway clones repository
    ↓
Railway builds (PHP setup)
    ↓
Railway injects env vars
    ↓
Railway deploys
    ↓
Production site live
```

### Post-Deployment Verification

**After Railway deployment completes:**

```bash
# 1. Check Railway logs
# View in Railway dashboard

# 2. Test production URL
curl -I https://your-railway-url.railway.app

# 3. Verify database connection
# Access /wp-admin and check functionality

# 4. Check wp-admin access
# Login at https://your-url/wp-admin

# 5. Test key pages
# Homepage, services, contact, etc.
```

### Build Troubleshooting

**Common Build Issues:**

1. **Deployment fails with "missing files":**
   - Check if required files were gitignored accidentally
   - Verify .gitignore isn't too aggressive
   - Ensure wp-content files are tracked

2. **Database connection error:**
   - Verify Railway MySQL plugin is installed
   - Check environment variables in Railway dashboard
   - Ensure WORDPRESS_DB_* variables are set

3. **White screen after deployment:**
   - Check Railway logs for PHP errors
   - Verify wp-config.php uses environment variables
   - Enable WORDPRESS_DEBUG temporarily

4. **Assets not loading (CSS/JS):**
   - Verify wp-content directory deployed
   - Check file permissions
   - Update site URL in wp_options table

### Build Performance

**Typical Build Times:**
- GitHub push: < 1 second
- Railway clone: 5-10 seconds
- PHP setup: 10-15 seconds
- Deployment: 5-10 seconds
- **Total: ~30-45 seconds**

**Optimization Tips:**
- Keep repository size small (exclude large media from git)
- Use Railway's CDN for static assets
- Leverage Railway's caching
- Minimize plugin count

## Git Ignore Verification Script

Create and run this script before every deployment:

```bash
#!/bin/bash
# verify-gitignore.sh

echo "🔍 Verifying gitignore configuration..."

# Check if sensitive files would be committed
SENSITIVE_FILES=(
    ".env"
    "wp-config.php"
    "*.sql"
    "docker-compose.yml"
    "Dockerfile"
)

ISSUES_FOUND=0

for pattern in "${SENSITIVE_FILES[@]}"; do
    if git ls-files | grep -q "$pattern"; then
        echo "❌ ERROR: $pattern is tracked by git!"
        ISSUES_FOUND=1
    else
        echo "✅ $pattern is properly ignored"
    fi
done

# Check for common sensitive patterns in tracked files
if git grep -l "password.*=.*['\"]" -- '*.php' '*.js' '*.env' 2>/dev/null; then
    echo "⚠️  WARNING: Found hardcoded passwords in tracked files"
    ISSUES_FOUND=1
fi

if [ $ISSUES_FOUND -eq 0 ]; then
    echo "✅ All checks passed! Safe to deploy."
    exit 0
else
    echo "❌ Issues found! DO NOT DEPLOY until fixed."
    exit 1
fi
```

Run before deployment:
```bash
chmod +x verify-gitignore.sh
./verify-gitignore.sh
```

## Deployment Checklist

Before pushing to GitHub (which triggers Railway deployment):

- [ ] Run `./verify-gitignore.sh` or manual git ignore check
- [ ] Verify `.env` is NOT in git: `git ls-files | grep .env`
- [ ] Verify `wp-config.php` is NOT in git: `git ls-files | grep wp-config.php`
- [ ] Verify no `.sql` files in git: `git ls-files | grep .sql`
- [ ] Verify no build docs with API keys are included
- [ ] Check `git status` shows only intended files
- [ ] Test locally on http://localhost:8090 before deploying
- [ ] Verify Railway environment variables are set
- [ ] Have database backup before deploying
- [ ] Commit message clearly describes changes
- [ ] Push to GitHub: `git push origin main`
- [ ] Monitor Railway deployment logs
- [ ] Test production site after deployment

## Common Commands

### Git Operations
```bash
# Check what files are tracked
git ls-files

# Check what would be ignored
git status --ignored

# Verify specific file is ignored
git check-ignore -v .env

# Remove accidentally committed file
git rm --cached .env
git commit -m "Remove sensitive file"
git push origin main

# View gitignore effectiveness
git status
```

### Docker Operations
```bash
# Start all services
sudo docker compose up -d

# Stop all services
sudo docker compose down

# View logs
sudo docker compose logs -f wordpress
sudo docker compose logs -f db

# Restart specific service
sudo docker compose restart wordpress

# Database backup
sudo docker compose exec db mysqldump -uwpuser -pwppassword wordpress > backup.sql

# Database restore
sudo docker compose exec db mysql -uwpuser -pwppassword wordpress < backup.sql
```

## Security Best Practices

1. **Credentials Management**
   - Never commit credentials to git
   - Use different credentials for local vs production
   - Rotate production credentials regularly
   - Use Railway's environment variables for secrets

2. **API Keys**
   - Store in `.env` file (gitignored)
   - Set in Railway dashboard for production
   - Never hardcode in PHP/JS files
   - Document which APIs are used (but not the keys)

3. **Database**
   - Never commit SQL dumps with real data
   - Use Railway's managed MySQL for production
   - Keep local dev data separate from production
   - Regular backups before deployments

4. **File Permissions**
   - WordPress files: 644
   - WordPress directories: 755
   - wp-config.php: 600 (production)

## Troubleshooting

### Local Development Issues

**WordPress shows database connection error:**
```bash
# Check containers are running
sudo docker compose ps

# Check database logs
sudo docker compose logs db

# Verify wp-config.php has correct credentials
```

**Port conflicts:**
```bash
# Check what's using the port
sudo lsof -i :8090

# Change port in .env file
# Restart containers
sudo docker compose down && sudo docker compose up -d
```

### Deployment Issues

**Railway deployment fails:**
- Check Railway logs in dashboard
- Verify environment variables are set
- Ensure no gitignored files are required for runtime
- Check MySQL connection in Railway

**Site shows errors after deployment:**
- Verify environment variables in Railway
- Check WordPress debug logs
- Ensure wp-content directory deployed correctly
- Verify database is accessible

## Support & Documentation

- Railway Docs: https://docs.railway.app/
- WordPress Docs: https://wordpress.org/documentation/
- Docker Compose: See `README-DOCKER.md`

## Project Maintainer Notes

- Always test changes locally before pushing to GitHub
- Railway auto-deploys on push to `main` branch
- Keep this CLAUDE.md file updated with any config changes
- Document any new API integrations (without exposing keys)
- Review and update `.gitignore` when adding new sensitive files
- Run security audit periodically

---

**Last Updated:** 2026-02-08
**Deployment Target:** Railway
**Local Development Port:** 8090
**Production URL:** [Set after Railway deployment]
