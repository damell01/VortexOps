#!/bin/bash

# VortexOps Pull & Optimize Script
# Quick script to pull changes and optimize

set -e

BRANCH="${1:-$(git rev-parse --abbrev-ref HEAD)}"

echo "════════════════════════════════════════════════════════════"
echo "VortexOps Pull & Optimize"
echo "Branch: $BRANCH"
echo "════════════════════════════════════════════════════════════"
echo ""

# Git operations
echo "📥 Pulling changes..."
git fetch origin
if ! git merge-base --is-ancestor HEAD "origin/$BRANCH"; then
    # This checkout has commits GitHub's $BRANCH doesn't (made on the server,
    # or left from an older history), so a fast-forward pull is impossible.
    if [ "${RESET_TO_ORIGIN:-0}" = "1" ]; then
        BACKUP="backup/server-$BRANCH-$(date +%Y%m%d-%H%M%S)"
        git branch "$BACKUP" HEAD
        git stash push -m "pull-and-optimize $BACKUP" >/dev/null 2>&1 || true
        git reset --hard "origin/$BRANCH"
        echo "↺ Reset to origin/$BRANCH. The server's old commits are kept on branch $BACKUP."
    else
        echo "✖ This checkout has diverged from origin/$BRANCH. Commits only on this server:"
        git log --oneline "origin/$BRANCH..HEAD" | head -20
        echo ""
        echo "  To match GitHub (the commits above are kept on a backup branch):"
        echo "    RESET_TO_ORIGIN=1 bash scripts/pull-and-optimize.sh $BRANCH"
        exit 1
    fi
else
    git pull origin "$BRANCH" --ff-only
fi
echo "✓ Changes pulled"
echo ""

# PHP dependencies
echo "📦 Installing PHP dependencies..."
composer install --no-interaction --prefer-dist --optimize-autoloader
echo "✓ PHP dependencies installed"
echo ""

# Node dependencies
echo "📦 Installing Node dependencies..."
npm ci
echo "✓ Node dependencies installed"
echo ""

# Build assets
echo "🎨 Building assets..."
npm run build
echo "✓ Assets built"
echo ""

# Clear and optimize
echo "⚡ Clearing caches..."
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
echo "✓ Caches cleared"
echo ""

echo "📦 Optimizing..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Filament keeps its own cache of registered pages, resources and icons, and
# it is not touched by view:cache or route:cache. A release that adds a page
# — the Handbook, Import Sheet — has it missing from the sidebar until this
# runs, which looks like the deploy silently skipped a file.
php artisan filament:optimize-clear
php artisan filament:optimize
echo "✓ Application optimized (Laravel and Filament)"
echo ""

# Screenshots and other public files are served straight off disk, so a new
# one is live as soon as it is pulled. Nothing to do for them — noted here
# because the handbook's pictures are the thing most likely to look stale,
# and a stale one means a browser cache, not a bad deploy.

# Database
echo "🗄️  Running migrations..."
php artisan migrate --force
echo "✓ Migrations complete"
echo ""

# Dev / staging only: keep the Fulfillment Center demo (shows mid-packing, an
# issue, a paused station, schedule, whiteboard activity). Rebuilt only when it
# is missing or a few days old, so your own clicking around is kept. The
# command refuses to run on a production site; FULFILLMENT_DEMO=0 skips it,
# FULFILLMENT_DEMO=fresh rebuilds it from scratch. It prints the demo manager
# and packer sign-ins (fulfillment-admin@ / fulfillment-worker@vortexops.test)
# and the walkthrough; `php artisan fulfillment:demo --logins` shows them again.
if [ "$BRANCH" != "Production" ] && [ "${FULFILLMENT_DEMO:-1}" != "0" ] && php artisan list --raw 2>/dev/null | grep -q '^fulfillment:demo'; then
    echo "🎬 Fulfillment demo..."
    if [ "${FULFILLMENT_DEMO:-1}" = "fresh" ]; then
        php artisan fulfillment:demo || echo "⚠ Fulfillment demo skipped"
    else
        php artisan fulfillment:demo --refresh || echo "⚠ Fulfillment demo skipped"
    fi
    echo ""
fi

# Long-running processes are still holding the old code.
#
# PHP-FPM caches compiled bytecode in OPcache, so until it is reloaded the
# site keeps serving the classes it read at boot — a pull that looks like it
# did nothing. Queue workers are worse: each one loaded the app once and
# keeps it in memory until it exits, so they carry the previous release
# until told otherwise.
echo "♻️  Reloading long-running processes..."

# The FPM unit is named for the PHP version, which differs between hosts.
FPM_UNIT="$(systemctl list-units --type=service --all --no-legend 'php*-fpm.service' 2>/dev/null | awk '{print $1}' | head -n1)"

if [ -n "$FPM_UNIT" ]; then
    if sudo -n systemctl reload "$FPM_UNIT" 2>/dev/null; then
        echo "✓ Reloaded $FPM_UNIT (OPcache cleared)"
    else
        echo "⚠ Could not reload $FPM_UNIT without a password. Run:"
        echo "    sudo systemctl reload $FPM_UNIT"
        echo "  Until you do, the site keeps serving the old code."
    fi
else
    echo "⚠ No php*-fpm service found — reload your web server by hand if it caches bytecode."
fi

# Signals every worker to finish its current job and exit; systemd starts
# a fresh one. Needs no sudo, and is a no-op when nothing is listening.
php artisan queue:restart
echo "✓ Queue workers signalled to restart"
echo ""

echo "════════════════════════════════════════════════════════════"
echo "✅ Pull & Optimize Complete!"
echo "════════════════════════════════════════════════════════════"
echo ""
echo "Deployed. If anything still looks like the old version, the FPM"
echo "reload above is the first thing to check."
echo ""
