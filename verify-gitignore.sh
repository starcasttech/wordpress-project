#!/bin/bash
# Git Ignore Verification Script
# Run this before EVERY deployment to ensure no sensitive data is committed

echo "🔍 Verifying gitignore configuration..."
echo "======================================"

# Check if sensitive files would be committed
SENSITIVE_FILES=(
    ".env"
    "wp-config.php"
    "*.sql"
    "docker-compose.yml"
    "Dockerfile"
    ".dockerignore"
    "*.log"
    "*.bak"
    "*.old"
)

ISSUES_FOUND=0

echo ""
echo "📋 Checking for sensitive files in git..."
for pattern in "${SENSITIVE_FILES[@]}"; do
    if git ls-files | grep -q "$pattern"; then
        echo "❌ ERROR: $pattern is tracked by git!"
        ISSUES_FOUND=1
    else
        echo "✅ $pattern is properly ignored"
    fi
done

echo ""
echo "🔑 Checking for hardcoded credentials..."
if git grep -l "password.*=.*['\"]" -- '*.php' '*.js' 2>/dev/null | grep -v wp-config; then
    echo "⚠️  WARNING: Found potential hardcoded passwords in tracked files"
    ISSUES_FOUND=1
else
    echo "✅ No hardcoded passwords found"
fi

echo ""
echo "🔐 Checking for API keys..."
if git grep -l "api[_-]key.*=.*['\"]" -- '*.php' '*.js' 2>/dev/null; then
    echo "⚠️  WARNING: Found potential API keys in tracked files"
    ISSUES_FOUND=1
else
    echo "✅ No hardcoded API keys found"
fi

echo ""
echo "📦 Listing files that will be pushed to GitHub:"
echo "=============================================="
git ls-files | head -20
TOTAL_FILES=$(git ls-files | wc -l)
echo "... ($TOTAL_FILES total files tracked)"

echo ""
echo "======================================"
if [ $ISSUES_FOUND -eq 0 ]; then
    echo "✅ All checks passed! Safe to deploy."
    echo ""
    echo "Next steps:"
    echo "  1. git add ."
    echo "  2. git commit -m 'Your message'"
    echo "  3. git push origin main"
    exit 0
else
    echo "❌ Issues found! DO NOT DEPLOY until fixed."
    echo ""
    echo "Fix issues by:"
    echo "  1. Add sensitive files to .gitignore"
    echo "  2. Remove them from git: git rm --cached <file>"
    echo "  3. Commit: git commit -m 'Remove sensitive files'"
    echo "  4. Run this script again"
    exit 1
fi
