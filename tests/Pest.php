<?php

declare(strict_types=1);

use Framework\Core\Dotenv;

/**
 * Load application bootstrap for all tests.
 * Defines ROOT_PATH, autoloader, environment, and services.
 */
require_once __DIR__.'/bootstrap.php';

$dotenvPath = ROOT_PATH.'/tests/.env.testing';

// Load test environment variables BEFORE anything else
if (!file_exists($dotenvPath)) {
    throw new RuntimeException('.env.testing file not found. Please create it based on .env.example');
}

$dotenv = new Dotenv();
$dotenv->load($dotenvPath);

if (!$dotenv->has('DB_HOST') || !$dotenv->has('DB_NAME') || !$dotenv->has('DB_USER')) {
    throw new RuntimeException('Missing DB_HOST, DB_NAME, or DB_USER in .env.testing');
}

/**
 * Global Pest configuration.
 *
 * Configures different test environments for Unit, Integration, and Feature tests
 * with appropriate setup/teardown hooks and isolation strategies.
 */
uses()->beforeEach(function () {
    $this->faker = \Faker\Factory::create();
})->in('Unit', 'Integration', 'Feature');

// ============================================================================
// UNIT TESTS - No database, all mocked, parallel execution
// ============================================================================

uses()
    ->beforeEach(function () {
        // Reset unique constraints to prevent ID collisions in parallel test execution
        if (class_exists('Faker\Factory')) {
            \Faker\Factory::create()->unique(true);
        }
    })
    ->afterEach(function () {
        // Close mock expectations to prevent memory leaks across test iterations
        if (class_exists('Mockery')) {
            Mockery::close();
        }
    })
    ->in('Unit');

// ============================================================================
// INTEGRATION TESTS - Real database with transaction rollback
// ============================================================================

uses()
    ->beforeEach(function () {
        // Load test database config (will use .env.testing variables)
        $dbConfig = require __DIR__.'/config/database.php';

        $this->db = new \Framework\Database($dbConfig);
        // Verify connection is established
        if (!$this->db->getConnection()) {
            throw new RuntimeException('Failed to establish database connection for tests');
        }

        // Reset unique constraints to prevent collisions
        \Faker\Factory::create()->unique(true);
    })
    ->afterEach(function () {
        try {
            if ($this->db->inTransaction()) {
                $this->db->rollback();
            }

            \Tests\Helpers\DatabaseHelper::cleanDatabase($this->db);

        } catch (\Exception $e) {
            error_log('Test cleanup failed: '.$e->getMessage());
        }
    })
    ->in('Integration');

// ============================================================================
// FEATURE TESTS - Full application bootstrap with HTTP simulation
// ============================================================================

uses()
    ->beforeEach(function () {
        // Clear Auth singleton cache and session before every Feature test
        $_SESSION = [];
        auth()->logout();

        // Load test database config (will use .env.testing variables)
        $dbConfig = require __DIR__.'/config/database.php';

        $this->db = new \Framework\Database($dbConfig);
        // Verify connection is established
        if (!$this->db->getConnection()) {
            throw new RuntimeException('Failed to establish database connection for tests');
        }

        // Use transaction for cleanup instead of migrations to preserve test speed
        $this->db->getConnection()->beginTransaction();

        // Destroy existing session to prevent state leakage between feature tests
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }

        // Clear cache to prevent stale data affecting HTTP response tests
        if (function_exists('cache_clear')) {
            cache_clear();
        }

        \Faker\Factory::create()->unique(true);
    })
    ->afterEach(function () {
        try {
            if ($this->db->getConnection()->inTransaction()) {
                $this->db->getConnection()->rollBack();
            }
        } catch (\Exception $e) {
            error_log('Feature test rollback failed: '.$e->getMessage());
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    })
    ->in('Feature');

// ============================================================================
// CUSTOM EXPECTATIONS
// ============================================================================

/**
 * Verify email format is valid.
 */
expect()->extend('toBeValidEmail', function () {
    $pattern = '/^[^\s@]+@[^\s@]+\.[^\s@]+$/';

    expect($this->value)->toMatch($pattern);

    return $this;
});

/**
 * Verify slug format is valid (lowercase, alphanumeric, hyphens).
 */
expect()->extend('toBeValidSlug', function () {
    $pattern = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    expect($this->value)->toMatch($pattern);

    return $this;
});

/**
 * Verify password meets security requirements.
 */
expect()->extend('toBeSecurePassword', function () {
    $value = $this->value;

    expect(strlen($value))->toBeGreaterThanOrEqual(8)
        ->and($value)->toMatch('/[A-Z]/')  // Has uppercase
        ->and($value)->toMatch('/[a-z]/')  // Has lowercase
        ->and($value)->toMatch('/[0-9]/'); // Has number

    return $this;
});

/**
 * Verify string is properly sanitized (no HTML tags).
 */
expect()->extend('toBeSanitized', function () {
    expect($this->value)->not->toMatch('/<[^>]*>/')
        ->and($this->value)->not->toContain('<script')
        ->and($this->value)->not->toContain('javascript:');

    return $this;
});

/**
 * Verify database transaction is active.
 */
expect()->extend('toHaveActiveTransaction', function () {
    expect($this->value->inTransaction())->toBeTrue();

    return $this;
});

/**
 * Verify cache key matches wildcard pattern.
 *
 * Converts pattern with asterisks to regex for case-insensitive matching.
 */
expect()->extend('toMatchCachePattern', function (string $pattern) {
    $key = $this->value;
    $regex = '/^'.str_replace('*', '.*', preg_quote($pattern, '/')).'$/i';

    return test()->assertTrue(
        (bool) preg_match($regex, $key),
        "Expected '{$key}' to match pattern '{$pattern}'"
    );
});

/*
|--------------------------------------------------------------------------
| Test Helpers
|--------------------------------------------------------------------------
*/

/**
 * Creates a real Request instance with sane test defaults.
 *
 * Always injects REMOTE_ADDR so ip()-dependent services (e.g. rate limiters)
 * receive a string instead of null.
 *
 * @param  array<string, mixed>  $post
 * @param  array<string, mixed>  $get
 * @param  array<string, mixed>  $server  Override or extend default server params
 */
function makeRequest(
    string $uri = '/',
    string $method = 'GET',
    array $post = [],
    array $get = [],
    array $server = []
): \Framework\Core\Request {
    $defaultServer = ['REMOTE_ADDR' => '127.0.0.1'];

    return new \Framework\Core\Request(
        $uri,
        $method,
        $get,
        $post,
        [],
        [],
        array_merge($defaultServer, $server), // caller can override if needed
        []
    );
}

/**
 * Setup controller with dependencies for testing.
 */
function setupController($controller, $request, $mockViewer): void
{
    $container = \Framework\Core\App::container();

    $controller->setRequest($request);
    $controller->setResponse(new \Framework\Core\Response());
    $controller->setViewer($mockViewer);
    $controller->setValidatorFactory($container->get('validator.factory'));
    $controller->setSession($container->get(\Framework\Session::class));
}

/**
 * Call controller method and catch validation exceptions.
 *
 * We simulate middleware behavior by catching ValidationException
 * and returning the redirect response automatically.
 */
function callController($controller, $method, $request, ...$args): \Framework\Core\Response
{
    try {
        return $controller->$method(...$args);
    } catch (\Framework\Exceptions\ValidationException $e) {
        // Simulate what middleware does
        $session = \Framework\Core\App::container()->get(\Framework\Session::class);

        $session->set('_errors', $e->errors());
        $oldInput = $request->all();
        unset($oldInput['password'], $oldInput['confirm_password'], $oldInput['_token']);
        $session->set('_old_input', $oldInput);

        $flash = $session->get('_flash', []);
        $flash['error'][] = 'Please correct the errors and try again.';
        $session->set('_flash', $flash);

        $referer = $request->header('referer') ?? '/';

        return (new \Framework\Core\Response())->redirect($referer);
    }
}

/**
 * Provide Faker instance for generating test data.
 *
 * We use a singleton pattern to avoid creating multiple Faker instances,
 * which improves test performance.
 */
function faker(): Faker\Generator
{
    static $faker = null;

    if ($faker === null) {
        $faker = Faker\Factory::create();
    }

    return $faker;
}
