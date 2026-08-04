#!/bin/bash

# VortexOps Development Setup Script
# Quick setup for local development

set -e

echo "════════════════════════════════════════════════════════════"
echo "VortexOps Development Setup"
echo "════════════════════════════════════════════════════════════"
echo ""

# Check if .env exists
if [ ! -f ".env" ]; then
    echo "📝 Creating .env file..."
    cp .env.example .env
    echo "⚠️  Update .env with your database credentials"
fi

# Generate key if needed
if ! grep -q "APP_KEY=base64:" .env; then
    echo "🔑 Generating application key..."
    php artisan key:generate
fi

# Install dependencies
echo "📦 Installing PHP dependencies..."
if [ ! -d "vendor" ]; then
    composer install
else
    composer update
fi
echo "✓ PHP dependencies installed"
echo ""

echo "📦 Installing Node dependencies..."
if [ ! -d "node_modules" ]; then
    npm install
else
    npm update
fi
echo "✓ Node dependencies installed"
echo ""

# Database setup
echo "🗄️  Setting up database..."
php artisan migrate:fresh --seed
echo "✓ Database ready"
echo ""

# Storage setup
echo "📁 Setting up storage..."
php artisan storage:link
chmod -R 775 storage bootstrap/cache
echo "✓ Storage ready"
echo ""

# Vite dev server setup
echo "🎨 Building assets..."
npm run dev &
DEV_PID=$!
echo "✓ Vite dev server started (PID: $DEV_PID)"
echo ""

echo "════════════════════════════════════════════════════════════"
echo "✅ Setup Complete!"
echo "════════════════════════════════════════════════════════════"
echo ""
echo "Start servers in separate terminals:"
echo ""
echo "Terminal 1 - Laravel development server:"
echo "  php artisan serve"
echo ""
echo "Terminal 2 - Queue worker (for notifications):"
echo "  php artisan queue:work --sleep=3 --tries=3"
echo ""
echo "Terminal 3 - Scheduler (for periodic tasks):"
echo "  while true; do php artisan schedule:run; sleep 60; done"
echo ""
echo "Terminal 4 - Vite (assets, already started):"
echo "  npm run dev"
echo ""
echo "Access the app at: http://localhost:8000"
echo ""
