#!/bin/bash
# Comprehensive Build & Deployment Verification Script
# Run this before EVERY deployment to Railway

echo "🏗️  Build & Deployment Verification"
echo "===================================="
echo ""

ISSUES_FOUND=0

# Check for build documentation
echo "📚 Checking for build documentation..."
BUILD_DOCS=$(git ls-files | grep -E "(docs/build|docs/api|docs/internal|BUILD\.md|API-KEYS\.md|DEVELOPMENT-NOTES\.md)" || echo "")
if [ -n "$BUILD_DOCS" ]; then
    echo "❌ ERROR: Build documentation found in git!"
    echo "$BUILD_DOCS"
    echo "These files should be in .gitignore"
    ISSUES_FOUND=1
else
    echo "✅ No build documentation in git"
fi

echo ""

# Check for sensitive files
echo "🔐 Running security checks..."
if [ -f "./verify-gitignore.sh" ]; then
    ./verify-gitignore.sh
    if [ $? -ne 0 ]; then
        ISSUES_FOUND=1
    fi
else
    echo "⚠️  WARNING: verify-gitignore.sh not found"
    echo "Running basic checks..."

    # Basic checks
    if git ls-files | grep -q "\.env"; then
        echo "❌ ERROR: .env file is tracked"
        ISSUES_FOUND=1
    fi

    if git ls-files | grep -q "wp-config\.php"; then
        echo "❌ ERROR: wp-config.php is tracked"
        ISSUES_FOUND=1
    fi

    if git ls-files | grep -q "\.sql"; then
        echo "❌ ERROR: SQL files are tracked"
        ISSUES_FOUND=1
    fi
fi

echo ""

# Verify WordPress core files present
echo "📦 Verifying WordPress structure..."
MISSING_FILES=()

if [ ! -f "wp-config-sample.php" ]; then
    MISSING_FILES+=("wp-config-sample.php")
fi

if [ ! -d "wp-admin" ]; then
    MISSING_FILES+=("wp-admin/")
fi

if [ ! -d "wp-content" ]; then
    MISSING_FILES+=("wp-content/")
fi

if [ ! -d "wp-includes" ]; then
    MISSING_FILES+=("wp-includes/")
fi

if [ ${#MISSING_FILES[@]} -eq 0 ]; then
    echo "✅ WordPress core structure present"
else
    echo "❌ ERROR: Missing WordPress files:"
    for file in "${MISSING_FILES[@]}"; do
        echo "   - $file"
    done
    ISSUES_FOUND=1
fi

echo ""

# Check for Docker files (should not be deployed)
echo "🐳 Checking for Docker files..."
DOCKER_FILES=$(git ls-files | grep -E "(docker-compose\.yml|Dockerfile|\.dockerignore)" || echo "")
if [ -n "$DOCKER_FILES" ]; then
    echo "⚠️  WARNING: Docker files found in git:"
    echo "$DOCKER_FILES"
    echo "These are for local development only and should be gitignored"
    ISSUES_FOUND=1
else
    echo "✅ No Docker files in git (good for Railway deployment)"
fi

echo ""

# Check file count and size
echo "📊 Repository statistics..."
FILE_COUNT=$(git ls-files | wc -l)
echo "   Total files to deploy: $FILE_COUNT"

if [ $FILE_COUNT -gt 10000 ]; then
    echo "⚠️  WARNING: Large number of files ($FILE_COUNT)"
    echo "   Consider using .gitignore to exclude unnecessary files"
fi

# Check for large files
echo "   Checking for large files..."
LARGE_FILES=$(git ls-files | xargs du -h 2>/dev/null | sort -rh | head -5)
if [ -n "$LARGE_FILES" ]; then
    echo "   Top 5 largest files:"
    echo "$LARGE_FILES" | sed 's/^/     /'
fi

echo ""

# Check git status
echo "📋 Git status check..."
STAGED_FILES=$(git diff --cached --name-only | wc -l)
UNSTAGED_FILES=$(git diff --name-only | wc -l)
UNTRACKED_FILES=$(git ls-files --others --exclude-standard | wc -l)

echo "   Staged files: $STAGED_FILES"
echo "   Unstaged changes: $UNSTAGED_FILES"
echo "   Untracked files: $UNTRACKED_FILES"

if [ $STAGED_FILES -eq 0 ] && [ $UNSTAGED_FILES -eq 0 ]; then
    echo "✅ Working directory clean"
fi

echo ""

# Final summary
echo "===================================="
if [ $ISSUES_FOUND -eq 0 ]; then
    echo "✅ All checks passed!"
    echo ""
    echo "📦 Ready for Railway deployment"
    echo ""
    echo "Next steps:"
    echo "  1. git add . (if needed)"
    echo "  2. git commit -m 'Your message'"
    echo "  3. git push origin main"
    echo "  4. Railway will auto-deploy"
    exit 0
else
    echo "❌ Issues found! DO NOT DEPLOY"
    echo ""
    echo "Fix the issues above before deploying to Railway"
    echo ""
    echo "Common fixes:"
    echo "  - Add sensitive files to .gitignore"
    echo "  - Remove files from git: git rm --cached <file>"
    echo "  - Move build docs out of repository"
    echo "  - Run: git commit -m 'Fix security issues'"
    exit 1
fi
