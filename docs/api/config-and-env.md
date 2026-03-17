## Configuration and Environment

Lexicon uses a small Dotenv wrapper and helper functions to load configuration from a `.env` file and convert common scalar types.

---

## Loading `.env`

The `Framework\Core\Dotenv` class is responsible for loading environment variables:

```php
<?php

use Framework\Core\Dotenv;

$dotenv = app(Dotenv::class);
$dotenv->load(__DIR__ . '/../.env');

// Or with overwrite and $_SERVER population
$dotenv->load(__DIR__ . '/../.env', true, true);
```

By default it populates `$_ENV` (and optionally `$_SERVER`) and supports type‑aware access.

---

## `env()` Helper

The `env()` helper is the most common way to read configuration:

```php
$debug       = env('APP_DEBUG');            // bool(true)
$name        = env('APP_NAME');             // string("My Blog")
$maxUploads  = env('MAX_UPLOADS');          // int(10)
$rateLimit   = env('RATE_LIMIT');           // float(5.5)
$featureFlag = env('FEATURE_FLAG');         // bool(false)
$apiKey      = env('API_KEY');              // null if not set
```

With defaults:

```php
$timeout  = env('REQUEST_TIMEOUT', 30);     // int(30) if not set
$timezone = env('APP_TIMEZONE', 'UTC');     // string('UTC')
```

---

## Typed Access via `Dotenv`

For explicit types, you can use the static helpers:

```php
use Framework\Core\Dotenv;

$isDebug    = Dotenv::getBool('APP_DEBUG', false);
$port       = Dotenv::getInt('SERVER_PORT', 80);
$multiplier = Dotenv::getFloat('RATE_MULTIPLIER', 1.0);
```

Check for presence:

```php
if (Dotenv::has('MAIL_HOST')) {
    $host = Dotenv::get('MAIL_HOST');
}
```

Obtain all variables as raw strings:

```php
$allVars = Dotenv::all();
```

---

## `.env` Examples

Example `.env` file with supported types:

```text
# Basic values
APP_NAME=My Blog
APP_URL=https://example.com

# Boolean values (convert to bool)
APP_DEBUG=true
APP_CACHE=false
FEATURE_PUBLISH=(true)
ENABLE_COMMENTS=(false)

# Null/empty
API_KEY=null
SKIP_VALIDATION=(null)
ALLOW_EMPTY=(empty)

# Numbers
MAX_UPLOADS=10
RATE_LIMIT=5.5
PORT=8080

# Quoted strings
DB_HOST="localhost"
DB_NAME='my_database'
DESCRIPTION="A blog with spaces"

# Variable expansion
DB_URL=mysql://${DB_HOST}:3306/${DB_NAME}
BASE_PATH=${APP_URL}/public
```

---

## Usage Guidelines

- Treat `.env` as environment‑specific; do not commit secrets.
- Use `env()` only in configuration/bootstrap; pass values into services, do not call `env()` deep inside domain logic.
- Keep environment names (`APP_ENV`, `APP_DEBUG`, `APP_URL`) consistent between local and production.

