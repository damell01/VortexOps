#!/bin/bash

# VortexOps Production Deployment Script
# Full production deployment with rollback safety

set -e

ENVIRONMENT="production"
TIMESTAMP=$(date '+%Y-%m-%d %H:%M:%S')
BACKUP_DIR="backups/$(date '+%Y%m%d_%H%M%S')"

echo "════════════════════════════════════════════════════════════"
echo "🚀 VortexOps Production Deployment"
echo "Timestamp: $TIMESTAMP"
echo "════════════════════════════════════════════════════════════"
echo ""

# Safety check
echo "⚠️  PRODUCTION DEPLOYMENT CONFIRMATION"
echo "────────────────────────────────────────"
read -p "Type 'DEPLOY' to confirm production deployment: " CONFIRM
if [ "$CONFIRM" != "DEPLOY" ]; then
    echo "❌ Deployment cancelled"
    exit 1
fi
echo ""

# Backup
echo "💾 Creating backup..."
mkdir -p "$BACKUP_DIR"
cp .env "$BACKUP_DIR/.env.backup" 2>/dev/null || true
mysqldump -u "$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" > "$BACKUP_DIR/database.sql" 2>/dev/null || true
echo "✓ Backup created at: $BACKUP_DIR"
echo ""

# Maintenance mode
echo "🔄 Enabling maintenance mode..."
php artisan down --secret=DeploymentInProgress
echo "✓ Maintenance mode enabled"
echo ""

# Git pull
echo "📥 Pulling latest changes..."
git fetch origin
git pull origin main --ff-only
echo "✓ Changes pulled"
echo ""

# Dependencies
echo "📦 Installing dependencies..."
composer install --no-dev --optimize-autoloader
npm ci
npm run build
echo "✓ Dependencies installed and built"
echo ""

# Optimization
echo "⚡ Optimizing application..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan filament:optimize-assets
echo "✓ Application optimized"
echo ""

# Database
echo "🗄️  Running migrations..."
php artisan migrate --force
echo "✓ Migrations complete"
echo ""

# Disable maintenance mode
echo "🎯 Disabling maintenance mode..."
php artisan up
echo "✓ Application live"
echo ""

echo "════════════════════════════════════════════════════════════"
echo "✅ Deployment Successful!"
echo "════════════════════════════════════════════════════════════"
echo ""
echo "Deployment Details:"
echo "  Timestamp: $TIMESTAMP"
echo "  Backup: $BACKUP_DIR"
echo "  Branch: $(git rev-parse --abbrev-ref HEAD)"
echo "  Commit: $(git rev-parse --short HEAD)"
echo ""
echo "Monitor:"
echo "  Logs: tail -f storage/logs/laravel.log"
echo "  Health: curl https://yourdomain.com/health"
echo ""
echo "To rollback (if needed):"
echo "  git revert <commit>"
echo "  ./scripts/production-deploy.sh"
echo ""
