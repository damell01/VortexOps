# VortexOps Deployment & Optimization Guide

Complete guide for deploying and maintaining the VortexOps application with mobile-optimized UI.

## 📱 Mobile Optimizations

The entire application has been optimized for mobile devices:

### Features
- **Touch-Friendly UI**: Minimum 44x44px touch targets (WCAG compliance)
- **Responsive Design**: Adapts to all screen sizes (mobile, tablet, desktop)
- **Safe Area Support**: iPhone notch and Dynamic Island compatibility
- **Landscape Mode**: Optimized for both portrait and landscape orientations
- **High DPI Support**: Crisp rendering on retina and high-resolution displays
- **Accessibility**: High contrast mode, reduced motion support
- **Performance**: Optimized for low bandwidth and slow connections

### Mobile Navigation
- Slide-out sidebar (hamburger menu) on mobile devices
- Quick search and notifications in mobile top bar
- Touch-optimized buttons and controls
- Smooth animations with reduced motion fallback

### Components
- `resources/css/mobile-optimizations.css` - Complete mobile CSS framework
- `resources/views/components/mobile-navigation.blade.php` - Mobile navigation component

## 🚀 Quick Start Commands

### After Pulling Changes
```bash
./scripts/pull-and-optimize.sh
```

This single command will:
1. Pull latest changes from your branch
2. Install PHP dependencies
3. Install Node dependencies
4. Build frontend assets
5. Clear and optimize caches
6. Run database migrations
7. Prepare the app for use

### Full Development Setup
```bash
./scripts/dev-setup.sh
```

Complete setup for local development including:
- Environment file creation
- Key generation
- Dependency installation
- Database setup with seeding
- Storage configuration

### Production Deployment
```bash
./scripts/production-deploy.sh
```

Production-grade deployment with:
- Database backups
- Maintenance mode
- Dependency optimization
- Asset compilation
- Safe migrations
- Rollback capability

## 📋 Available Scripts

### `pull-and-optimize.sh`
**Purpose**: Quick update after pulling changes  
**Usage**: `./scripts/pull-and-optimize.sh [branch]`  
**Time**: ~2-5 minutes

Pulls changes and runs optimization pipeline. Safest option for regular updates.

### `dev-setup.sh`
**Purpose**: Initial development environment setup  
**Usage**: `./scripts/dev-setup.sh`  
**Time**: ~5-10 minutes

One-time setup script. Creates `.env`, installs dependencies, seeds database.

### `deploy-optimize.sh`
**Purpose**: General optimization and deployment  
**Usage**: `./scripts/deploy-optimize.sh [production|staging]`  
**Time**: ~3-8 minutes

Useful for manual deployments or when you need to run optimization without pulling.

### `production-deploy.sh`
**Purpose**: Production-grade deployment with safety features  
**Usage**: `./scripts/production-deploy.sh`  
**Time**: ~5-10 minutes

**⚠️ Requires confirmation before proceeding**

Safe production deployment with:
- Database backups before migration
- Maintenance mode during deployment
- Git pull with fast-forward only
- Optimized builds
- Easy rollback support

## 🔧 Manual Deployment Steps

If you prefer manual control:

```bash
# 1. Pull changes
git fetch origin
git pull origin your-branch

# 2. Install dependencies
composer install --optimize-autoloader
npm ci

# 3. Build assets
npm run build

# 4. Clear caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# 5. Optimize
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan filament:optimize-assets

# 6. Database
php artisan migrate --force

# 7. Done!
php artisan serve
```

## 📊 Environment Setup

### Required Environment Variables

```bash
APP_NAME=VortexOps
APP_ENV=production
APP_DEBUG=false
APP_URL=https://yourdomain.com

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=vortexops
DB_USERNAME=root
DB_PASSWORD=your_password

CACHE_DRIVER=redis
SESSION_DRIVER=database
QUEUE_CONNECTION=database

WHATNOT_EMAIL=your_email@example.com
WHATNOT_PASSWORD=your_password
WHATNOT_LIMIT=50
```

### Optional Services

```bash
# AI/ML Features
OLLAMA_API_URL=http://localhost:11434

# AWS S3 Storage
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=

# Email
MAIL_DRIVER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=
MAIL_PASSWORD=
```

## 🎯 New Mobile Scanner Feature

### Access Points
- **URL**: `/admin/mobile-scanner-app`
- **Navigation**: Inventory > Mobile Scanner (Priority: Sort 1)
- **Modes**: 
  - Receive: Scan items from pallets
  - Quick Add: Add stock to locations
  - Lookup: View item details

### Features
- 📱 Phone camera barcode detection
- ✨ Real-time visual feedback
- 🔊 Audio/haptic feedback
- 💾 Session persistence (survives page refresh)
- 📊 Item detail display
- 🎯 Three scanning workflows

### Camera Support
- EAN-13/8 (retail barcodes)
- Code 128/39 (industrial)
- UPC-A/E (retail)
- QR codes
- Manual fallback input

## 📈 Performance Optimization

### What the scripts do:
1. **Config Caching**: Faster config lookups
2. **Route Caching**: Faster route resolution  
3. **View Caching**: Pre-compiled Blade templates
4. **Autoloader Optimization**: Faster class loading
5. **Asset Minification**: Smaller CSS/JS files
6. **Asset Versioning**: Cache-busting for updates

### Cache Warming
Common data is pre-loaded on deployment:
- Application settings
- Navigation structure
- Module configuration

## ✅ Post-Deployment Checklist

After any deployment:
- [ ] Run `php artisan test` to verify functionality
- [ ] Check `/health` endpoint for application status
- [ ] Test on mobile device (iOS/Android)
- [ ] Verify camera scanning works
- [ ] Check all navigation links
- [ ] Review error logs: `tail -f storage/logs/laravel.log`
- [ ] Test main workflows (receiving, inventory, reports)

## 🔄 Rollback Procedure

If something goes wrong:

```bash
# Option 1: Via backups
cd backups/YYYYMMDD_HHMMSS
mysql -u root -p database_name < database.sql
cp .env.backup /path/to/app/.env

# Option 2: Via git
git revert <commit-hash>
./scripts/production-deploy.sh

# Option 3: Manual
git reset --hard <previous-commit>
php artisan migrate:rollback
./scripts/pull-and-optimize.sh
```

## 📱 Mobile Testing Checklist

Test on actual mobile devices:

**Camera Scanning**
- [ ] Start camera on receive mode
- [ ] Scan various barcode formats
- [ ] Verify beep/vibration feedback
- [ ] Test success and error states

**Navigation**
- [ ] Hamburger menu opens/closes
- [ ] Menu closes on link click
- [ ] Quick search works
- [ ] Notifications icon visible

**Forms & Input**
- [ ] Touch targets are adequately sized
- [ ] Keyboard appears on input focus
- [ ] Form submits without zoom
- [ ] Labels visible above inputs

**Responsiveness**
- [ ] Portrait orientation works
- [ ] Landscape orientation works
- [ ] No horizontal scrolling
- [ ] Tables scroll horizontally

**Performance**
- [ ] Page loads quickly
- [ ] Smooth scrolling
- [ ] No layout jank
- [ ] Touch feedback is immediate

## 🐛 Troubleshooting

### Scripts won't execute
```bash
chmod +x scripts/*.sh
./scripts/pull-and-optimize.sh
```

### Database migration fails
```bash
# Check what's pending
php artisan migrate:status

# Rollback and retry
php artisan migrate:rollback
php artisan migrate --force
```

### Assets don't load
```bash
# Rebuild assets
npm run build

# Or with Vite dev server
npm run dev
```

### Camera doesn't work
1. Check browser permissions (Settings > Permissions > Camera)
2. Ensure HTTPS in production
3. Test in a fresh browser window
4. Try a different barcode format

### Mobile menu doesn't open
1. Clear browser cache (Cmd/Ctrl + Shift + R)
2. Check browser console for errors
3. Verify JavaScript is enabled
4. Test in different browser

## 📚 Additional Resources

- [Laravel Documentation](https://laravel.com/docs)
- [Filament Documentation](https://filamentphp.com/docs)
- [Tailwind CSS](https://tailwindcss.com)
- [BarcodeDetector API](https://developer.mozilla.org/en-US/docs/Web/API/BarcodeDetector)

## 🆘 Support

For issues:
1. Check the logs: `tail -f storage/logs/laravel.log`
2. Run tests: `php artisan test`
3. Check database: `php artisan tinker`
4. Review git status: `git status && git log --oneline -5`

## 📝 Notes

- All scripts are non-destructive by default
- Backups are created before production deploys
- Maintenance mode prevents user access during deployment
- Migrations are run automatically
- Caches are cleared and rebuilt for each deploy

---

**Last Updated**: 2026-08-04  
**Branch**: `claude/whatnot-shipments-scraper-zsuf1m`  
**VortexOps v1.0**
