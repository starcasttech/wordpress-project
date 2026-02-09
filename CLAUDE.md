# WordPress ISP Site - Claude Reference

## Project Overview
WordPress ISP (Internet Service Provider) site deployed to Railway via GitHub.

## Technology Stack
- WordPress (PHP 8.2 + Apache)
- MySQL 8.0
- Docker (local dev only)
- Railway (production deployment)

## Sensitive Files Location (NOT IN GIT)
All sensitive documentation, credentials, and build references are stored in:
`/home/leonard/projects/wordpress-private/`

This folder structure:
- `wordpress-private/api-docs/` - API documentation and keys
- `wordpress-private/build-docs/` - Build and deployment notes
- `wordpress-private/credentials/` - Environment files, passwords, tokens

**Reference this location when you need:**
- API keys or credentials
- Internal build documentation  
- Development notes with secrets
- Local .env files

## What's in Git (Public Repository)
✅ WordPress core files
✅ Custom theme and plugins
✅ Public configuration examples (.env.example)
✅ Deployment documentation (without secrets)

## What's NOT in Git (Gitignored)
❌ .env files with real credentials
❌ wp-config.php with database passwords
❌ Database dumps (*.sql)
❌ Backup files
❌ Docker config (local dev only)
❌ Build docs with API keys
❌ Internal reference documentation

## Local Development
```bash
# Start
sudo docker compose up -d

# Access
# WordPress: http://localhost:8090
# phpMyAdmin: http://localhost:8081

# Stop  
sudo docker compose down
```

## Railway Deployment
1. Push to GitHub main branch
2. Railway auto-deploys
3. Set environment variables in Railway dashboard (see wordpress-private/credentials/)

## Pre-Commit Safety Check
```bash
# Check what will be committed
git status

# Verify sensitive files are ignored
git check-ignore -v .env wp-config.php

# List all tracked files
git ls-files | grep -E '\.env|\.sql|BUILD|API|SECRET' && echo "❌ SENSITIVE FILE FOUND" || echo "✅ Clean"
```

## Notes for Claude
- Always check `/home/leonard/projects/wordpress-private/` for credentials/API docs
- Never commit files from wordpress-private/ to git
- Local dev passwords are examples only (wppassword, rootpassword)
- Production credentials are in Railway dashboard and wordpress-private/
