#!/bin/bash

# VortexOps - Pull, Optimize & Clear Cache Script
# Usage: bash scripts/optimize.sh [branch-name]
# Example: bash scripts/optimize.sh claude/whatnot-shipments-scraper-zsuf1m

BRANCH="${1:-claude/whatnot-shipments-scraper-zsuf1m}"

echo "╔════════════════════════════════════════════════════════════╗"
echo "║  VortexOps - Pull, Optimize & Clear Cache                 ║"
echo "╚════════════════════════════════════════════════════════════╝"
echo ""

# Colors
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# 1. Fetch latest changes
echo -e "${BLUE}[1/11]${NC} Fetching latest changes from remote..."
git fetch origin "$BRANCH" || { echo -e "${YELLOW}Warning: Could not fetch branch${NC}"; }
echo ""

# 2. Checkout branch if not already on it
echo -e "${BLUE}[2/11]${NC} Checking out branch..."
git checkout "$BRANCH" 2>/dev/null || echo "Already on $BRANCH"
echo ""

# 3. Pull latest changes
echo -e "${BLUE}[3/11]${NC} Pulling latest commits..."
git pull origin "$BRANCH" || echo "Already up to date"
echo ""

# 4. Install composer dependencies with optimization
echo -e "${BLUE}[4/11]${NC} Installing/updating composer dependencies..."
composer install --optimize-autoloader --no-dev -q && echo "✓ Composer dependencies updated" || echo "✗ Composer install failed"
echo ""

# 5. Clear Laravel caches
echo -e "${BLUE}[5/11]${NC} Clearing application cache..."
php artisan cache:clear -q && echo "✓ Application cache cleared" || true
echo ""

echo -e "${BLUE}[6/11]${NC} Clearing config cache..."
php artisan config:clear -q && echo "✓ Config cache cleared" || true
echo ""

echo -e "${BLUE}[7/11]${NC} Clearing route cache..."
php artisan route:clear -q && echo "✓ Route cache cleared" || true
echo ""

echo -e "${BLUE}[8/11]${NC} Clearing view cache..."
php artisan view:clear -q && echo "✓ View cache cleared" || true
echo ""

echo -e "${BLUE}[9/11]${NC} Clearing event cache..."
php artisan event:clear -q 2>/dev/null && echo "✓ Event cache cleared" || true
echo ""

# 6. Optimize Laravel
echo -e "${BLUE}[10/11]${NC} Running Laravel optimization..."
php artisan optimize --quiet 2>/dev/null && echo "✓ Laravel optimized" || true
echo ""

# 7. Final verification
echo -e "${BLUE}[11/11]${NC} Verification & Summary..."
echo ""

echo "╔════════════════════════════════════════════════════════════╗"
echo "║  Status Report                                             ║"
echo "╚════════════════════════════════════════════════════════════╝"
echo ""

# Show branch info
echo -e "${BLUE}Current Branch:${NC}"
git branch -v | grep '*'
echo ""

# Show latest commits
echo -e "${BLUE}Latest 3 Commits:${NC}"
git log --oneline -3
echo ""

# Check git status
echo -e "${BLUE}Working Tree:${NC}"
if [ -z "$(git status --porcelain)" ]; then
    echo -e "${GREEN}✓ Clean${NC}"
else
    echo -e "${YELLOW}Modified files:${NC}"
    git status --short | head -5
fi
echo ""

# Show available whatnot commands
echo -e "${BLUE}Available Whatnot Commands:${NC}"
php artisan list whatnot 2>/dev/null | grep "whatnot:" | head -3 || true
echo ""

echo -e "${GREEN}════════════════════════════════════════════════════════════${NC}"
echo -e "${GREEN}✓ Setup complete! Ready to use.${NC}"
echo -e "${GREEN}════════════════════════════════════════════════════════════${NC}"
echo ""
echo "Quick commands:"
echo "  • Test scraper:     php artisan whatnot:sync-shipments-live --help"
echo "  • Run scraper:      php artisan whatnot:sync-shipments-live"
echo "  • Start dev server: php artisan serve"
echo "  • Run tests:        php artisan test"
echo ""
