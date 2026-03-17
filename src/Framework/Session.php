<?php

declare(strict_types=1);

namespace Framework;

/**
 * Session management with security best practices.
 *
 * Provides secure session handling with HttpOnly cookies, HTTPS enforcement,
 * SameSite protection, and session regeneration capabilities.
 */
class Session
{
    private bool $enabled;

    /**
     * Initialize session with secure parameters.
     *
     * Sessions are disabled in CLI and cache warming contexts to avoid
     * file locking issues and unnecessary overhead.
     */
    public function __construct()
    {
        $isCli = PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg';
        $isCacheWarming = isset($_ENV['CACHE_WARMING']) && $_ENV['CACHE_WARMING'] === 'true';

        if ($isCli || $isCacheWarming) {
            $this->enabled = false;
            if (!isset($_SESSION)) {
                $_SESSION = [];
            }

            return;
        }

        $this->enabled = true;

        if (session_status() === PHP_SESSION_NONE) {
            $this->configure();
            session_start();
        }
    }

    /**
     * Configure secure session cookie parameters and validation rules.
     *
     * Applies defense-in-depth by configuring multiple security layers:
     * HttpOnly cookies prevent XSS theft, Secure flag protects HTTPS connections,
     * SameSite=Lax mitigates CSRF attacks.
     */
    protected function configure(): void
    {
        $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        $params = session_get_cookie_params();

        session_set_cookie_params([
            'lifetime' => $params['lifetime'],
            'path' => $params['path'] ?: '/',
            'domain' => $params['domain'],
            'secure' => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        // Reject uninitialized session IDs to prevent fixation attacks
        ini_set('session.use_strict_mode', '1');

        // Prevent session IDs in URLs
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');

        // Cache middleware controls all cache headers
        ini_set('session.cache_limiter', '');
    }

    /**
     * Store a value in the session.
     *
     * @param  string  $key  Session key
     * @param  mixed  $value  Value to store
     */
    public function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    /**
     * Retrieve a session value.
     *
     * @param  string  $key  Session key
     * @param  mixed  $default  Default value if key doesn't exist
     * @return mixed The session value or default
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    /**
     * Check if a session key exists.
     *
     * Uses array_key_exists to differentiate between non-existent and null values.
     *
     * @param  string  $key  Session key
     * @return bool True if key exists
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $_SESSION);
    }

    /**
     * Remove a session value.
     *
     * @param  string  $key  Session key to remove
     */
    public function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }

    /**
     * Remove a session value.
     *
     * Alias for remove() to provide Laravel-like API consistency.
     *
     * @param  string  $key  Session key to remove
     */
    public function delete(string $key): void
    {
        $this->remove($key);
    }

    /**
     * Retrieve and remove a value in one operation.
     *
     * Useful for flash messages and temporary data that should only be read once.
     *
     * @param  string  $key  Session key
     * @param  mixed  $default  Default value if key doesn't exist
     * @return mixed The session value or default
     */
    public function pull(string $key, mixed $default = null): mixed
    {
        $value = $this->get($key, $default);
        $this->remove($key);

        return $value;
    }

    /**
     * Clear all session data.
     *
     * Removes all variables but keeps the session alive.
     */
    public function clear(): void
    {
        $_SESSION = [];
    }

    /**
     * Destroy the session completely.
     *
     * Removes session data, destroys the session file, and deletes the session cookie.
     * Use during logout to completely terminate the session.
     */
    public function destroy(): void
    {
        $_SESSION = [];

        // Delete cookie only if sessions are enabled and cookies are in use
        if ($this->enabled && ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        if ($this->enabled) {
            session_destroy();
        }
    }

    /**
     * Regenerate session ID to prevent fixation attacks.
     *
     * Call this after authentication state changes such as login, logout,
     * or privilege escalation.
     *
     * @param  bool  $deleteOldSession  Whether to delete the old session file
     */
    public function regenerate(bool $deleteOldSession = true): void
    {
        if ($this->enabled) {
            session_regenerate_id($deleteOldSession);
        }
    }

    /**
     * Get the current session ID.
     *
     * @return string Session ID, or empty string if sessions are disabled
     */
    public function id(): string
    {
        return $this->enabled ? session_id() : '';
    }

    /**
     * Get all session data.
     *
     * Primarily for debugging; avoid using in production logic.
     *
     * @return array<string, mixed> All session data
     */
    public function all(): array
    {
        return $_SESSION;
    }

    /**
     * Store data for the next request only.
     *
     * Flash data is automatically removed after being retrieved once.
     *
     * @param  string  $key  Flash key
     * @param  mixed  $value  Flash value
     */
    public function flash(string $key, mixed $value): void
    {
        $this->set('_flash_new.'.$key, $value);
    }

    /**
     * Retrieve flash data.
     *
     * Checks both old flash (from previous request) and new flash (from current request).
     *
     * @param  string  $key  Flash key
     * @param  mixed  $default  Default value if key doesn't exist
     * @return mixed The flash value or default
     */
    public function getFlash(string $key, mixed $default = null): mixed
    {
        // Check old flash first (from previous request)
        if ($this->has('_flash_old.'.$key)) {
            return $this->get('_flash_old.'.$key, $default);
        }

        return $this->get('_flash_new.'.$key, $default);
    }

    /**
     * Age flash data by moving new flash to old and removing expired flash.
     *
     * Should be called at the end of each request by middleware to maintain
     * the flash lifecycle.
     */
    public function ageFlashData(): void
    {
        // Remove expired flash data
        foreach ($_SESSION as $key => $value) {
            if (str_starts_with($key, '_flash_old.')) {
                unset($_SESSION[$key]);
            }
        }

        // Age current flash data
        foreach ($_SESSION as $key => $value) {
            if (str_starts_with($key, '_flash_new.')) {
                $newKey = str_replace('_flash_new.', '_flash_old.', $key);
                $_SESSION[$newKey] = $value;
                unset($_SESSION[$key]);
            }
        }
    }

    /**
     * Check if sessions are enabled.
     *
     * Sessions are disabled in CLI and cache warming contexts.
     *
     * @return bool True if sessions are enabled
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }
}
