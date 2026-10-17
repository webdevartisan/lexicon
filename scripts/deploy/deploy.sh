#!/bin/bash

# Lexicon Blog - Production Deployment Script
#
# We automate the deployment process including:
# - Code updates from Git
# - Database backup
# - Dependency installation
# - Cache management
# - Service reloads
#
# Usage: ./scripts/deploy/deploy.sh

set -e  # Exit on any error

echo "🚀 Starting deployment..."
echo ""

# We capture the project root
PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$PROJECT_ROOT"

# ============================================================================
# STEP 1: Maintenance Mode
# ============================================================================

# The gate reads storage/maintenance.json; a bare {} payload is enough
echo "🔒 Enabling maintenance mode..."
echo '{}' > storage/maintenance.json
echo ""

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
# STEP 4: Back Up the Database
# ============================================================================

# There is no migration runner: SQL files in database/migrations are applied by
# hand, so this dump is the rollback point for that manual step.
echo "🗄️  Backing up the database..."

env_value() {
    grep -E "^$1=" .env | head -n 1 | cut -d= -f2- | tr -d '"'"'"''
}

DB_HOST="$(env_value DB_HOST)"
DB_NAME="$(env_value DB_NAME)"
DB_USER="$(env_value DB_USER)"
DB_PASSWORD="$(env_value DB_PASSWORD)"

(umask 077; mkdir -p storage/backups && chmod 700 storage/backups)
BACKUP_FILE="storage/backups/pre-deploy-$(date +%Y%m%d-%H%M%S).sql"

# umask 077 so the dump is never readable by others, even briefly
(umask 077; MYSQL_PWD="$DB_PASSWORD" mysqldump --single-transaction -h "$DB_HOST" -u "$DB_USER" "$DB_NAME" > "$BACKUP_FILE")

echo "✅ Backup written to $BACKUP_FILE"
echo "ℹ️  Apply any new database/migrations/*.sql files by hand before going live"
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

echo "🔓 Disabling maintenance mode..."
rm -f storage/maintenance.json
echo ""

# ============================================================================
# SUCCESS
# ============================================================================

echo "═══════════════════════════════════════"
echo "🎉 Deployment Complete!"
echo "═══════════════════════════════════════"
echo ""
echo "✅ Code updated"
echo "✅ Dependencies installed"
echo "✅ Database backed up"
echo "✅ Cache warmed"
echo "✅ Services reloaded"
echo ""
