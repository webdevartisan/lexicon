## Getting Started

Lexicon is a Laravel‑inspired blogging platform built on a custom PHP 8.1+ framework. This guide summarizes the fastest way to get a local environment running and points you to deeper documentation.

---

## Requirements

- **PHP**: 8.1+ with `pdo_mysql`, `mbstring`, `json`
- **Database**: MySQL 5.7+ or MariaDB 10.3+
- **Composer**: 2.0+
- **Node.js**: 18+ and npm 9+ (for control‑panel/frontend assets)

See `docs/setup/install-composer.md` if you need help installing Composer.

---

## Installation

### 1. Clone the repository

```bash
git clone https://github.com/yourusername/lexicon.git
cd lexicon
```

### 2. Automated setup (recommended)

On **Windows**:

```cmd
install.bat
```

On **Linux/macOS**:

```bash
chmod +x install.sh
./install.sh
```

These scripts:

- Install PHP/composer dependencies
- Run the initial setup script
- Prepare storage directories

### 3. Manual setup (alternative)

```bash
composer install
npm install

php scripts/setup/setup.php

npm run build:cp-css
```

Configure your `.env` file (copy from an example if present) and set:

- Database connection settings
- `APP_URL`, `APP_ENV`, `APP_DEBUG`

For environment variable usage details, see `docs/api/config-and-env.md`.

---

## Running the Application

From the project root:

```bash
php -S localhost:8000 -t public
```

Then open `http://localhost:8000` in your browser.

---

## Common Composer Scripts

These scripts are defined in `composer.json`:

```bash
# Run all tests
composer test

# Reset database and reseed
composer fresh

# Clear and warm application caches
composer cache:clear
composer cache:warm
```

For more details on the full test and validation workflow, see `docs/testing.md` and `scripts/README.md`.

---

## Where to Go Next

- **Architecture overview**: `docs/architecture.md`
- **Testing strategy**: `docs/testing.md`
- **Database schema**: `docs/database/schema.md`
- **API and modules**: files under `docs/api/`

