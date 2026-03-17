# Scripts Overview

This directory contains utility scripts for development, setup, testing, and deployment.

---

## 1. Deployment Scripts (`deploy/`)

### `deploy-check.bat`
Runs pre-deployment checks to ensure the environment is ready.

### `deploy.sh`
Deployment script (currently untested). Handles deployment steps for Unix-like systems.

---

## 2. Maintenance Scripts (`maintenance/`)

### `fix.bat`
Automatically fixes common issues:
- Applies Pint code style fixes
- Clears caches
- Regenerates Composer autoload files

### `quick-test.bat`
Runs a fast validation suite:
- Pint (test mode)
- Pest tests

### `test.bat`
Runs the full local validation suite:
- Validate `composer.json`
- Pint code style check
- PHPStan static analysis
- Composer audit
- Pest tests
- Pest coverage (min 60%)

---

## 3. Setup Scripts (`setup/`)

### `setup.php`
Initial application setup script.

---

## 4. Utility Scripts (`utilities/`)

### `render-icons.js`
Server-side Lucide icon renderer. Pre-renders icons so they are cached before client requests.

---

## 5. Root-Level Installers

### `/install.bat`
Windows installation script.

### `/install.sh`
Unix installation script.

---

## Usage Notes

- Run **installation scripts** from the project root.
- Run **maintenance scripts** during development or before committing.
- Run **deployment scripts** only in deployment environments.
- All scripts assume dependencies are installed (Composer, Node, etc.).
