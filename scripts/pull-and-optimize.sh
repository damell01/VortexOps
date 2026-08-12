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
git pull origin "$BRANCH" --ff-only
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
echo "✓ Application optimized"
echo ""

# Database
echo "🗄️  Running migrations..."
php artisan migrate --force
echo "✓ Migrations complete"
echo ""

echo "════════════════════════════════════════════════════════════"
echo "✅ Pull & Optimize Complete!"
echo "════════════════════════════════════════════════════════════"
echo ""
echo "Ready to use! Start with:"
echo "  php artisan serve"
echo ""
