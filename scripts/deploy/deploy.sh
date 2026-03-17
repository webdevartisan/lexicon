#!/bin/bash

# Lexicon Blog - Production Deployment Script
#
# We automate the deployment process including:
# - Code updates from Git
# - Dependency installation
# - Cache management
# - Service reloads
#
# Usage: ./scripts/deploy.sh

set -e  # Exit on any error

echo "🚀 Starting deployment..."
echo ""

# We capture the project root
PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$PROJECT_ROOT"

# ============================================================================
# STEP 1: Maintenance Mode (Optional)
# ============================================================================

# Uncomment if you have a maintenance mode feature
# echo "🔒 Enabling maintenance mode..."
# touch storage/maintenance
# echo ""

# ============================================================================
# STEP 2: Pull Latest Code
# ============================================================================

echo "📥 Pulling latest code from Git..."
git pull origin main

if [ $? -ne 0 ]; then
    echo "❌ Git pull failed"
    exit 1
fi

echo "✅ Code updated"
echo ""

# ============================================================================
# STEP 3: Install Dependencies
# ============================================================================

echo "📦 Installing PHP dependencies..."
composer install --no-dev --optimize-autoloader --no-interaction

if [ $? -ne 0 ]; then
    echo "❌ Composer install failed"
    exit 1
fi

echo "✅ Dependencies installed"
echo ""

# ============================================================================
# STEP 4: Run Database Migrations
# ============================================================================

echo "🗄️  Running database migrations..."
php cli db:migrate

if [ $? -ne 0 ]; then
    echo "❌ Migrations failed"
    exit 1
fi

echo "✅ Migrations applied"
echo ""

# ============================================================================
# STEP 5: Clear & Warm Cache
# ============================================================================

echo "🗑️  Clearing old cache..."
php cli cache:clear

echo "🔥 Warming up cache..."
php cli cache:warm

echo "✅ Cache refreshed"
echo ""

# ============================================================================
# STEP 6: Reload Services
# ============================================================================

echo "🔄 Reloading PHP-FPM..."

# We detect which PHP-FPM service is running
if systemctl is-active --quiet php8.3-fpm; then
    sudo systemctl reload php8.3-fpm
elif systemctl is-active --quiet php8.2-fpm; then
    sudo systemctl reload php8.2-fpm
elif systemctl is-active --quiet php-fpm; then
    sudo systemctl reload php-fpm
else
    echo "⚠️  PHP-FPM service not found, skipping reload"
fi

echo "✅ Services reloaded"
echo ""

# ============================================================================
# STEP 7: Disable Maintenance Mode
# ============================================================================

# Uncomment if you have a maintenance mode feature
# echo "🔓 Disabling maintenance mode..."
# rm -f storage/maintenance
# echo ""

# ============================================================================
# SUCCESS
# ============================================================================

echo "═══════════════════════════════════════"
echo "🎉 Deployment Complete!"
echo "═══════════════════════════════════════"
echo ""
echo "✅ Code updated"
echo "✅ Dependencies installed"
echo "✅ Database migrated"
echo "✅ Cache warmed"
echo "✅ Services reloaded"
echo ""
