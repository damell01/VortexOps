#!/bin/bash

# VortexOps Deployment & Optimization Script
# Usage: ./scripts/deploy-optimize.sh [production|staging]

set -e

ENVIRONMENT="${1:-staging}"
TIMESTAMP=$(date '+%Y-%m-%d %H:%M:%S')

echo "════════════════════════════════════════════════════════════"
echo "VortexOps Deployment & Optimization Script"
echo "Environment: $ENVIRONMENT"
echo "Timestamp: $TIMESTAMP"
echo "════════════════════════════════════════════════════════════"
echo ""

# ──────────────────────────────────────────────────────────────
# 1. CLEAR ALL CACHES
# ──────────────────────────────────────────────────────────────
echo "📦 Clearing caches..."
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
echo "✓ Caches cleared"
echo ""

# ──────────────────────────────────────────────────────────────
# 2. OPTIMIZE APPLICATION
# ──────────────────────────────────────────────────────────────
echo "⚡ Optimizing application..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
echo "✓ Application optimized"
echo ""

# ──────────────────────────────────────────────────────────────
# 3. BUILD FRONTEND ASSETS
# ──────────────────────────────────────────────────────────────
echo "🎨 Building frontend assets..."
if [ -f "package.json" ]; then
    if [ "$ENVIRONMENT" = "production" ]; then
        npm run build
        echo "✓ Production build complete"
    else
        npm run dev
        echo "✓ Development build complete"
    fi
else
    echo "⚠ No package.json found, skipping frontend build"
fi
echo ""

# ──────────────────────────────────────────────────────────────
# 4. DATABASE OPERATIONS
# ──────────────────────────────────────────────────────────────
echo "🗄️ Database operations..."
php artisan migrate --force
php artisan db:seed --class=DatabaseSeeder --force 2>/dev/null || true
echo "✓ Database updated"
echo ""

# ──────────────────────────────────────────────────────────────
# 5. STORAGE & LINKS
# ──────────────────────────────────────────────────────────────
echo "📁 Setting up storage..."
php artisan storage:link
chmod -R 775 storage bootstrap/cache
echo "✓ Storage configured"
echo ""

# ──────────────────────────────────────────────────────────────
# 6. FILAMENT COMMANDS
# ──────────────────────────────────────────────────────────────
echo "🎯 Filament optimizations..."
php artisan filament:optimize-assets
echo "✓ Filament optimized"
echo ""

# ──────────────────────────────────────────────────────────────
# 7. QUEUE OPTIMIZATION
# ──────────────────────────────────────────────────────────────
echo "🔄 Queue optimization..."
php artisan queue:failed-table
php artisan queue:batches-table
echo "✓ Queue tables created"
echo ""

# ──────────────────────────────────────────────────────────────
# 8. CACHE WARMING (Optional)
# ──────────────────────────────────────────────────────────────
echo "🔥 Warming caches..."
# Pre-load commonly used queries
php artisan tinker << 'EOF' 2>/dev/null || true
\App\Models\Setting::all();
\App\Support\AdminModules::visibleNavigationGroups();
exit
EOF
echo "✓ Caches warmed"
echo ""

# ──────────────────────────────────────────────────────────────
# 9. GENERATE ENVIRONMENT REPORT
# ──────────────────────────────────────────────────────────────
echo "📊 Environment Report"
echo "────────────────────"
echo "PHP Version: $(php -v | head -n 1)"
echo "Laravel Version: $(php artisan --version)"
echo "Environment: $ENVIRONMENT"
echo "App Debug: $(php artisan tinker --execute="echo config('app.debug') ? 'ON' : 'OFF';" 2>/dev/null || echo 'Unknown')"
echo ""

# ──────────────────────────────────────────────────────────────
# 10. FINAL STATUS
# ──────────────────────────────────────────────────────────────
echo "════════════════════════════════════════════════════════════"
echo "✅ Deployment Complete!"
echo "════════════════════════════════════════════════════════════"
echo ""
echo "Next steps:"
echo "1. Run tests: php artisan test"
echo "2. Check health: curl http://localhost:8000/health"
echo "3. View logs: tail -f storage/logs/laravel.log"
echo ""
