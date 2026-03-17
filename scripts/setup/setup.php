<?php

declare(strict_types=1);

/**
 * Application setup script.
 *
 * Handles first‑time installation and incremental updates:
 * - Database creation and dedicated user provisioning
 * - .env configuration and APP_KEY generation
 * - Running pending migrations
 * - Seeding initial data (admin user, site settings) when first run
 *
 * This script is safe to run multiple times; it detects existing .env and migrations.
 */

define('ROOT_PATH', dirname(dirname(__DIR__)));

require_once ROOT_PATH . '/vendor/autoload.php';

use Framework\Core\Dotenv;
use Framework\Helpers\KeyGenerator;


// ============================================================================
// SIGNAL HANDLING (Graceful Ctrl+C handling)
// ============================================================================

/**
 * Register signal handlers for clean setup cancellation.
 *
 * On Unix/Linux, catches SIGINT (Ctrl+C) and SIGTERM (kill) to avoid
 * partial .env files or inconsistent database states during setup.
 */
if (function_exists('pcntl_signal')) {
    pcntl_signal(SIGINT, function () {
        echo "\n\n❌ Setup interrupted by user (Ctrl+C)\n";
        echo "   No changes were saved.\n\n";
        exit(130);
    });

    pcntl_signal(SIGTERM, function () {
        echo "\n\n❌ Setup terminated\n";
        echo "   No changes were saved.\n\n";
        exit(143);
    });
}

// Enable async signals so handlers run immediately.
if (function_exists('pcntl_async_signals')) {
    pcntl_async_signals(true);
}


// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

/**
 * Prompt for password with hidden input on supported systems.
 *
 * On Unix/Linux, uses `stty -echo` to hide typing.
 * On Windows, input is visible (cmd.exe limitation).
 *
 * @param  string  $prompt  Prompt text to show.
 * @return string           Password entered by user.
 *
 * @throws \RuntimeException If user cancels input (Ctrl+C).
 */
function promptSilent(string $prompt = 'Enter Password:'): string
{
    if (strncasecmp(PHP_OS, 'WIN', 3) === 0) {
        echo $prompt.' ';
        $input = fgets(STDIN);

        if ($input === false) {
            echo "\n\n❌ Setup cancelled by user\n";
            exit(130);
        }

        return trim($input);
    }

    echo $prompt.' ';
    system('stty -echo');
    $password = fgets(STDIN);
    system('stty echo');
    echo PHP_EOL;

    if ($password === false) {
        echo "\n❌ Setup cancelled by user\n";
        exit(130);
    }

    return trim($password);
}

/**
 * Prompt for generic input, optionally with a default.
 *
 * @param  string      $prompt  Prompt text.
 * @param  string|null $default Optional default value.
 * @return string               User input or default.
 *
 * @throws \RuntimeException If user cancels input (Ctrl+C).
 */
function promptInput(string $prompt, ?string $default = null): string
{
    if ($default !== null) {
        echo "{$prompt} [{$default}]: ";
    } else {
        echo "{$prompt}: ";
    }

    $input = fgets(STDIN);

    if ($input === false) {
        echo "\n\n❌ Setup cancelled by user\n";
        exit(130);
    }

    $input = trim($input);

    return ($input === '' && $default !== null) ? $default : $input;
}

/**
 * Prompt yes/no confirmation with default.
 *
 * @param  string $prompt  Question text.
 * @param  bool   $default Default answer (true = yes, false = no).
 * @return bool           True if user agrees, false otherwise.
 *
 * @throws \RuntimeException If user cancels input (Ctrl+C).
 */
function promptConfirm(string $prompt, bool $default = true): bool
{
    $defaultText = $default ? 'Y/n' : 'y/N';
    echo "{$prompt} [{$defaultText}]: ";

    $input = fgets(STDIN);

    if ($input === false) {
        echo "\n\n❌ Setup cancelled by user\n";
        exit(130);
    }

    $input = strtolower(trim($input));

    if ($input === '') {
        return $default;
    }

    return in_array($input, ['y', 'yes', '1', 'true']);
}

/**
 * Detect if this is a first‑time run.
 *
 * Returns true if:
 * - .env does not exist, or
 * - .env is missing key DB/APP configuration entries.
 *
 * @return bool True if first‑time setup is required.
 */
function isFirstRun(): bool
{
    $envPath = ROOT_PATH.'/.env';

    if (!file_exists($envPath)) {
        return true;
    }

    $envContent = file_get_contents($envPath);

    // If any of these are missing, treat as first run.
    return !preg_match('/DB_NAME=\w+/', $envContent) ||
           !preg_match('/DB_USER=\w+/', $envContent) ||
           !preg_match('/APP_KEY=\S+/', $envContent);
}

/**
 * Render full .env file from configuration array.
 *
 * Outputs a consistent .env structure for dev and production settings.
 *
 * @param  array<string, mixed> $config Configuration values.
 * @return string               Complete .env content.
 */
function generateEnvFile(array $config): string
{
    $env = $config['environment'] ?? 'development';
    $isProduction = ($env === 'production');

    return <<<ENV
# Application Configuration
APP_NAME={$config['app_name']}
APP_URL={$config['app_url']}
APP_ENV={$env}
APP_DEBUG={$config['debug']}
APP_KEY={$config['app_key']}

# Database Configuration
DB_HOST={$config['db_host']}
DB_NAME={$config['db_name']}
DB_USER={$config['db_user']}
DB_PASSWORD={$config['db_password']}

# Error Handling
SHOW_ERRORS={$config['show_errors']}

# Security & Performance
FORCE_HTTPS={$config['force_https']}
FORCE_WWW={$config['force_www']}
MAINTENANCE={$config['maintenance']}
MAINTENANCE_ALLOW="{$config['maintenance_allow']}"

# Mail Configuration
MAIL_ENABLED=false
MAIL_DRIVER={$config['mail_driver']}
MAIL_HOST={$config['mail_host']}
MAIL_PORT={$config['mail_port']}
MAIL_USERNAME={$config['mail_username']}
MAIL_PASSWORD={$config['mail_password']}
MAIL_ENCRYPTION={$config['mail_encryption']}
MAIL_FROM_ADDRESS={$config['mail_from']}
MAIL_FROM_NAME="{$config['mail_from_name']}"
MAIL_DEBUG={$config['mail_debug']}

# Cache Configuration
CACHE_ENABLED={$config['cache_enabled']}
CACHE_GC_PROBABILITY={$config['cache_gc_prob']}
CACHE_GC_DIVISOR={$config['cache_gc_div']}
CACHE_MAX_FILES={$config['cache_max_files']}

# Query Logging (disable in production)
DB_LOG_QUERIES=false

# Slow Query Logging (minimal performance impact)
DB_LOG_SLOW_QUERIES=true
DB_SLOW_QUERY_THRESHOLD=1.0

ENV;
}


// ============================================================================
// MAIN EXECUTION
// ============================================================================

try {
    $envPath = ROOT_PATH . '/.env';
    $firstRun = isFirstRun();

    // ------------------------------------------------------------------------
    // STEP 1: Environment Setup (First Run Only)
    // ------------------------------------------------------------------------

    if ($firstRun) {
        echo "🚀 First-time setup detected.\n\n";
        echo "═══════════════════════════════════════\n";
        echo "  ENVIRONMENT CONFIGURATION\n";
        echo "═══════════════════════════════════════\n\n";

        $appName = promptInput('Application name', 'Lexicon');
        $appUrl = promptInput('Application URL', 'http://localhost');

        echo "\n";
        echo "Select environment:\n";
        echo "  1) Development (debug enabled, detailed errors)\n";
        echo "  2) Production (debug disabled, secure settings)\n";
        $envChoice = promptInput('Environment', '1');

        $isProduction = ($envChoice === '2');
        $environment = $isProduction ? 'production' : 'development';
        $debug = $isProduction ? 'false' : 'true';
        $showErrors = $isProduction ? '0' : '1';
        $mailDebug = $isProduction ? 'false' : 'true';

        echo "\n";
        echo "═══════════════════════════════════════\n";
        echo "  DATABASE CONFIGURATION\n";
        echo "═══════════════════════════════════════\n\n";

        $rootUser = promptInput('MySQL root username', 'root');
        $rootPass = promptSilent('MySQL root password');

        echo "\n";

        $dbHost = promptInput('Database host', 'localhost');
        $dbName = promptInput('Database name', 'lexicon_blog');
        $appUser = promptInput('Database username', 'lexicon_user');
        $appPass = promptSilent('Database password');

        echo "\n";

        // Create PDO connection to root to bootstrap database and user.
        try {
            $pdoRoot = new PDO("mysql:host={$dbHost}", $rootUser, $rootPass);
            $pdoRoot->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            echo "✅ Connected to MySQL server\n";
        } catch (PDOException $e) {
            echo "❌ Failed to connect to MySQL server\n";
            echo '   Error: '.$e->getMessage()."\n";
            echo "   Please check your MySQL root credentials and try again.\n";
            exit(1);
        }

        // Create database if missing.
        try {
            $pdoRoot->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            echo "✅ Database '{$dbName}' created\n";
        } catch (PDOException $e) {
            echo "❌ Failed to create database\n";
            echo '   Error: '.$e->getMessage()."\n";
            exit(1);
        }

        // Drop user first if it exists, then create and grant privileges.
        try {
            $pdoRoot->exec("DROP USER IF EXISTS '{$appUser}'@'{$dbHost}'");
            $pdoRoot->exec("CREATE USER '{$appUser}'@'{$dbHost}' IDENTIFIED BY '{$appPass}'");
            $pdoRoot->exec("GRANT ALL PRIVILEGES ON `{$dbName}`.* TO '{$appUser}'@'{$dbHost}'");
            $pdoRoot->exec('FLUSH PRIVILEGES');
            echo "✅ Database user '{$appUser}' created\n\n";
        } catch (PDOException $e) {
            echo "❌ Failed to create database user\n";
            echo '   Error: '.$e->getMessage()."\n";
            exit(1);
        }

        $pdoRoot = null;

        echo "🔐 Generating application key...\n";
        $appKey = KeyGenerator::generateForEnv(32);
        echo "✅ Application key generated\n\n";

        echo "═══════════════════════════════════════\n";
        echo "  MAIL CONFIGURATION\n";
        echo "═══════════════════════════════════════\n\n";

        $configureMail = promptConfirm('Configure mail settings now?', false);

        if ($configureMail) {
            $mailDriver = promptInput('Mail driver', 'smtp');
            $mailHost = promptInput('SMTP host', 'smtp.mailtrap.io');
            $mailPort = promptInput('SMTP port', '2525');
            $mailUsername = promptInput('SMTP username', '');
            $mailPassword = promptSilent('SMTP password');
            $mailEncryption = promptInput('Encryption (tls/ssl)', 'tls');
            $mailFrom = promptInput('From address', "noreply@{$appUrl}");
            $mailFromName = promptInput('From name', $appName);
        } else {
            echo "⏩ Skipping mail configuration (you can configure later in .env)\n";
            $mailDriver = 'smtp';
            $mailHost = 'smtp.mailtrap.io';
            $mailPort = '2525';
            $mailUsername = '';
            $mailPassword = '';
            $mailEncryption = 'tls';
            $mailFrom = 'noreply@localhost.test';
            $mailFromName = $appName;
        }

        echo "\n";

        // Build unified configuration for .env rendering.
        $config = [
            'app_name' => $appName,
            'app_url' => $appUrl,
            'environment' => $environment,
            'debug' => $debug,
            'show_errors' => $showErrors,
            'app_key' => $appKey,
            'db_host' => $dbHost,
            'db_name' => $dbName,
            'db_user' => $appUser,
            'db_password' => $appPass,
            'force_https' => $isProduction ? '1' : '0',
            'force_www' => '0',
            'maintenance' => '0',
            'maintenance_allow' => '127.0.0.1,10.0.0.0/8,192.168.0.0/16',
            'mail_driver' => $mailDriver,
            'mail_host' => $mailHost,
            'mail_port' => $mailPort,
            'mail_username' => $mailUsername,
            'mail_password' => $mailPassword,
            'mail_encryption' => $mailEncryption,
            'mail_from' => $mailFrom,
            'mail_from_name' => $mailFromName,
            'mail_debug' => $mailDebug,
            'cache_enabled' => 'true',
            'cache_gc_prob' => '1',
            'cache_gc_div' => '100',
            'cache_max_files' => '5000',
        ];

        // Emit .env and write to disk.
        $envContent = generateEnvFile($config);
        file_put_contents($envPath, $envContent);
        echo "✅ Configuration saved to .env\n\n";

    } else {
        echo "✅ Existing configuration found\n\n";
    }

    // ------------------------------------------------------------------------
    // STEP 2: Load Environment & Connect to Database
    // ------------------------------------------------------------------------

    echo "═══════════════════════════════════════\n";
    echo "  LOADING CONFIGURATION\n";
    echo "═══════════════════════════════════════\n\n";

    $dotenv = new Dotenv();
    $dotenv->load($envPath);

    $dbHost = $_ENV['DB_HOST'] ?? 'localhost';
    $dbName = $_ENV['DB_NAME'] ?? '';
    $appUser = $_ENV['DB_USER'] ?? '';
    $appPass = $_ENV['DB_PASSWORD'] ?? '';

    if (empty($dbName) || empty($appUser)) {
        echo "❌ Invalid .env configuration\n";
        echo "   Please delete .env and run setup again, or check your configuration.\n";
        exit(1);
    }

    echo "📋 Connecting to database: {$dbName}\n";

    try {
        $pdo = new PDO(
            "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4",
            $appUser,
            $appPass,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4',
            ]
        );
        echo "✅ Connected to database\n\n";
    } catch (PDOException $e) {
        echo "❌ Failed to connect to database '{$dbName}'\n";
        echo '   Error: '.$e->getMessage()."\n\n";
        echo "💡 Troubleshooting:\n";
        echo "   - Check if database '{$dbName}' exists\n";
        echo "   - Verify credentials in .env file\n";
        echo "   - To start fresh, delete .env and run setup again\n\n";
        exit(1);
    }

    // ------------------------------------------------------------------------
    // STEP 3: Run Database Migrations
    // ------------------------------------------------------------------------

    echo "═══════════════════════════════════════\n";
    echo "  RUNNING MIGRATIONS\n";
    echo "═══════════════════════════════════════\n\n";

    // Ensure migrations tracking table exists.
    $pdo->exec('CREATE TABLE IF NOT EXISTS migrations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        filename VARCHAR(255) NOT NULL UNIQUE,
        applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_filename (filename)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

    // Load list of already applied migrations.
    $stmt = $pdo->query('SELECT filename FROM migrations ORDER BY filename');
    $applied = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $migrationDir = ROOT_PATH . '/database/migrations';

    if (!is_dir($migrationDir)) {
        echo "⚠️  No migrations directory found\n";
        echo "   Creating directory: {$migrationDir}\n";
        mkdir($migrationDir, 0755, true);
    }

    $files = glob($migrationDir . '/*.sql');

    if (empty($files)) {
        echo "⚠️  No migration files found\n";
        echo "   Place your SQL migration files in: {$migrationDir}\n\n";
    } else {
        sort($files);

        $newMigrationsRun = 0;

        foreach ($files as $file) {
            $filename = basename($file);

            if (in_array($filename, $applied)) {
                echo "⏩ Skipped: {$filename}\n";
                continue;
            }

            // Apply migration script.
            try {
                $sql = file_get_contents($file);
                $pdo->exec($sql);

                $stmt = $pdo->prepare('INSERT INTO migrations (filename) VALUES (?)');
                $stmt->execute([$filename]);

                echo "✅ Applied: {$filename}\n";
                $newMigrationsRun++;
            } catch (PDOException $e) {
                echo "❌ Failed to apply: {$filename}\n";
                echo '   Error: '.$e->getMessage()."\n";
                exit(1);
            }
        }

        if ($newMigrationsRun === 0) {
            echo "✅ All migrations up to date\n";
        }
    }

    echo "\n";

    // ------------------------------------------------------------------------
    // STEP 4: Seed Initial Data (First Run Only)
    // ------------------------------------------------------------------------

    if ($firstRun) {
        echo "═══════════════════════════════════════\n";
        echo "  INITIAL DATA SETUP\n";
        echo "═══════════════════════════════════════\n\n";

        // Check if users table exists before seeding; avoids INSERT failures on missing schema.
        $tables = $pdo->query("SHOW TABLES LIKE 'users'")->fetchAll();

        if (empty($tables)) {
            echo "⚠️  Users table not found\n";
            echo "   Please create migration files first\n\n";
        } else {
            echo "👤 Creating admin user...\n\n";

            $adminUsername = promptInput('Admin username', 'admin');
            $adminEmail = promptInput('Admin email', 'admin@example.com');
            $adminPass = promptSilent('Admin password');

            $hash = password_hash($adminPass, PASSWORD_BCRYPT, ['cost' => 12]);

            try {
                $stmt = $pdo->prepare('INSERT INTO users (username, email, password, created_at) VALUES (?, ?, ?, NOW())');
                $stmt->execute([$adminUsername, $adminEmail, $hash]);

                $adminId = (int) $pdo->lastInsertId();

                // Assign admin role (assuming role_id=1 is “admin”) via user_roles.
                $stmt = $pdo->prepare('INSERT INTO user_roles (user_id, role_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE role_id = role_id');
                $stmt->execute([$adminId, 1]);

                echo "✅ Admin user created: {$adminEmail}\n\n";
            } catch (PDOException $e) {
                echo "⚠️  Could not create admin user (may already exist)\n";
                echo '   Error: '.$e->getMessage()."\n\n";
            }

            // Seed site settings if settings table exists.
            $settingsTable = $pdo->query("SHOW TABLES LIKE 'settings'")->fetchAll();

            if (!empty($settingsTable)) {
                echo "📝 Configuring site settings...\n\n";

                $siteName = promptInput('Site name', $_ENV['APP_NAME'] ?? 'Lexicon Blog');
                $siteDescription = promptInput('Site description', 'A modern PHP blog');

                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO settings (name, value)
                        VALUES ('site_name', ?), ('site_description', ?)
                        ON DUPLICATE KEY UPDATE value = VALUES(value)
                    ");
                    $stmt->execute([$siteName, $siteDescription]);

                    echo "✅ Site settings saved\n\n";
                } catch (PDOException $e) {
                    echo "⚠️  Could not save site settings\n";
                    echo '   Error: '.$e->getMessage()."\n\n";
                }
            }
        }
    }

    // ------------------------------------------------------------------------
    // SUCCESS
    // ------------------------------------------------------------------------

    echo "═══════════════════════════════════════\n";
    echo "🎉 Setup Complete!\n";
    echo "═══════════════════════════════════════\n\n";

    if ($firstRun) {
        echo "Next steps:\n";
        echo "  1. Configure your web server (Apache/Nginx)\n";
        echo '  2. Point document root to: '.ROOT_PATH.'/public'."\n";
        echo "  3. Restart your web server\n";
        echo "  4. Visit your site and log in!\n\n";
        echo "📝 Configuration saved to: .env\n";
        echo "🔑 Keep your .env file secure - never commit it to version control!\n\n";
    } else {
        echo "All migrations applied successfully.\n";
        echo "Your application is up to date.\n\n";
    }

    exit(0);

} catch (PDOException $e) {
    echo "\n❌ Database Error: ".$e->getMessage()."\n\n";
    exit(1);
} catch (Exception $e) {
    echo "\n❌ Error: ".$e->getMessage()."\n\n";
    exit(1);
}
