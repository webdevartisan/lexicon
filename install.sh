#!/bin/bash

# Lexicon Blog Installation Script
# Verifies system requirements, installs PHP and Node.js dependencies,
# sets permissions, and runs the application setup.

set -e  # Exit immediately on any error to prevent partial installs.

echo "🚀 Lexicon Blog Installation"
echo "============================"
echo ""

# Determine the project root from the script’s directory.
PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$PROJECT_ROOT"

# ============================================================================
# STEP 1: Check System Requirements
# ============================================================================

echo "📋 Checking system requirements..."
echo ""

# Ensure PHP is installed for Composer and framework execution.
if ! command -v php &> /dev/null; then
    echo "❌ PHP is not installed"
    echo "   Install: sudo apt install php-cli php-mbstring php-xml php-mysql php-curl"
    exit 1
fi

PHP_VERSION=$(php -r "echo PHP_VERSION;")
echo "✅ PHP: $PHP_VERSION"

# Enforce PHP 8.1+ because the application uses language features from that version.
if ! php -r "exit(version_compare(PHP_VERSION, '8.1.0', '>=') ? 0 : 1);"; then
    echo "❌ PHP 8.1+ is required (found: $PHP_VERSION)"
    exit 1
fi

# Verify required PHP extensions for database access, Unicode strings, and JSON.
REQUIRED_EXTENSIONS=("pdo" "pdo_mysql" "mbstring" "json")
MISSING_EXTENSIONS=()

for ext in "${REQUIRED_EXTENSIONS[@]}"; do
    if ! php -m | grep -q "^$ext$"; then
        MISSING_EXTENSIONS+=("$ext")
    fi
done

if [ ${#MISSING_EXTENSIONS[@]} -gt 0 ]; then
    echo "❌ Missing PHP extensions: ${MISSING_EXTENSIONS[*]}"
    echo "   Install: sudo apt install php-${MISSING_EXTENSIONS[0]}"
    exit 1
fi

echo "✅ PHP extensions: OK"

# Check Node.js availability; if missing, offer to install it for icon rendering.
if ! command -v node &> /dev/null; then
    echo "⚠️  Node.js is not installed"
    echo ""
    read -p "   Install Node.js now? (y/n): " -n 1 -r
    echo ""

    if [[ $REPLY =~ ^[Yy]$ ]]; then
        echo "📦 Installing Node.js..."
        curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
        sudo apt-get install -y nodejs
        echo "✅ Node.js installed"
        NODE_INSTALLED=true
    else
        echo "⚠️  Continuing without Node.js (icons will render client‑side)"
        NODE_INSTALLED=false
    fi
else
    NODE_VERSION=$(node --version)
    echo "✅ Node.js: $NODE_VERSION"
    NODE_INSTALLED=true
fi

# Ensure npm is available if Node.js is installed (required for build scripts).
if [ "$NODE_INSTALLED" != false ] && ! command -v npm &> /dev/null; then
    echo "❌ npm is not installed"
    echo "   Install: sudo apt install npm"
    exit 1
fi

# Report npm version if Node is present, to help with debugging.
if [ "$NODE_INSTALLED" != false ]; then
    NPM_VERSION=$(npm --version)
    echo "✅ npm: v$NPM_VERSION"
fi

echo ""

# ============================================================================
# STEP 2: Install Composer Dependencies
# ============================================================================

echo "📦 Installing PHP dependencies..."

# Require Composer because it manages autoloading and PHP package resolution.
if ! command -v composer &> /dev/null; then
    echo "❌ Composer is not installed"
    echo "   Install: https://getcomposer.org/download/"
    exit 1
fi

# Install only production dependencies, optimized for performance.
composer install --no-dev --optimize-autoloader

echo "✅ PHP dependencies installed"
echo ""

# ============================================================================
# STEP 3: Install Node.js Dependencies
# ============================================================================

# Install Node.js dependencies only if Node is available.
if [ "$NODE_INSTALLED" != false ]; then
    echo "📦 Installing Node.js dependencies..."
    npm install
    echo "✅ Node.js dependencies installed"
    echo ""

    # Warn if icon‑rendering dependencies are missing, which may break SSR icons.
    if [ ! -d "node_modules/lucide" ]; then
        echo "⚠️  Warning: lucide package not installed"
    fi

    if [ ! -d "node_modules/jsdom" ]; then
        echo "⚠️  Warning: jsdom package not installed"
    fi

    if [ ! -f "scripts/render-icons.js" ]; then
        echo "⚠️  Warning: render-icons.js script not found"
    fi
fi

# ============================================================================
# STEP 4: Set Permissions
# ============================================================================

echo "🔐 Setting permissions..."

# Create storage directories for logs, cache, and user uploads.
mkdir -p storage/cache
mkdir -p storage/logs
mkdir -p storage/uploads

# Set world‑readable but only owner‑writable permissions for shared servers.
chmod -R 775 storage
# Make the icon‑rendering script executable if present.
chmod +x scripts/render-icons.js 2>/dev/null || true

echo "✅ Permissions set"
echo ""

# ============================================================================
# STEP 5: Run Application Setup
# ============================================================================

echo "🎯 Running application setup..."
echo ""

# Attempt to locate setup.php in likely locations.
if [ -f "setup.php" ]; then
    php setup.php
elif [ -f "scripts/setup/setup.php" ]; then
    php scripts/setup/setup.php
else
    echo "❌ setup.php not found in root or scripts folder"
    exit 1
fi

if [ $? -ne 0 ]; then
    echo ""
    echo "❌ Setup failed"
    exit 1
fi

# ============================================================================
# FINAL SUMMARY
# ============================================================================

echo ""
echo "═══════════════════════════════════════════════════"
echo "🎉 Installation Complete!"
echo "═══════════════════════════════════════════════════"
echo ""
echo "✅ All dependencies installed"
echo "✅ Application configured"
echo ""
echo "📌 Next steps:"
echo "   1. Configure your web server (Apache/Nginx)"
echo "   2. Point document root to: $PROJECT_ROOT/public"
echo "   3. Restart your web server"
echo "   4. Visit your site!"
echo ""

# If Node.js is not installed, icon rendering will fall back to client‑side JS.
if [ "$NODE_INSTALLED" = false ]; then
    echo "⚠️  Icon rendering is disabled (Node.js not installed)"
    echo "   To enable: sudo apt install nodejs npm && npm install"
    echo ""
fi
