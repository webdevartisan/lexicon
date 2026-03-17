# Lexicon

> ⚠️ **Alpha — APIs, database schemas, and internal conventions are subject to
> breaking changes without notice. Not production-ready.**

![Status](https://img.shields.io/badge/status-alpha-orange)
![Version](https://img.shields.io/badge/version-0.1.0--alpha-blue)
![PHP](https://img.shields.io/badge/PHP-8.1+-777BB4?logo=php)
![License](https://img.shields.io/badge/license-GPL--v2-green)

A custom Laravel-inspired MVC blogging platform built from scratch in PHP 8.1+.
Lexicon provides a lightweight, self-hosted blogging platform with a
clean routing system, a custom template engine, role-based access, and CSRF/security handling.

**Developer docs are in [`docs/README.md`](docs/README.md).**

---

## Requirements

| Dependency | Minimum version |
|---|---|
| PHP | 8.1+ with `PDO`, `pdo_mysql`, `mbstring`, `json` |
| MySQL / MariaDB | MySQL 5.7+ or MariaDB 10.3+ |
| Composer | 2.0+ |
| Node.js | 18+ |
| npm | 9+ |

---

## Installation

### Option A — Automated setup (recommended)

**Windows:**
```cmd
install.bat
```

**Linux / macOS:**

```bash
chmod +x install.sh && ./install.sh
```


### Option B — Manual setup

```bash
# 1. Clone the repository
git clone https://github.com/webdevartisan/lexicon.git
cd lexicon

# 2. Install PHP and JS dependencies
composer install --no-dev --optimize-autoloader
npm install

# 3. Run the setup wizard (creates .env, runs migrations)
php scripts/setup/setup.php

# 4. Build and copy frontend assets
npm run build:cp-css
```


---

## Quick Start

```bash
# Start a local development server
php -S localhost:8000 -t public

# Run the test suite
composer test

# Wipe and re-seed the database (destructive)
composer fresh
```


---

## Developer Documentation

All architecture, API, database, and security documentation lives under
`docs/`. Start here:

[`docs/README.md`](docs/README.md) — full index and onboarding guide.

Key sections:

- [`docs/getting-started.md`](docs/getting-started.md) — environment setup
- [`docs/architecture.md`](docs/architecture.md) — framework layers and request lifecycle
- [`docs/api/security-and-csrf.md`](docs/api/security-and-csrf.md) — CSRF strategy
- [`docs/database/schema.md`](docs/database/schema.md) — database schema

---

## Contributing

Since Lexicon is in **alpha**, please:

- **Read [`docs/README.md`](docs/README.md) before contributing** — it maps all
subsystems and their current stability.
- **Open an issue or discussion before large structural changes.**
- **Do not assume API stability** — always check the relevant `docs/api/` file
  before building on top of any subsystem.

---

## License

Lexicon is licensed under the **GPL v2 or later**. See [`LICENSE.txt`](LICENSE.txt).