<?php

declare(strict_types=1);

namespace Framework;

use Framework\Core\Request;
use Framework\Session\SessionDriver;
use Framework\Session\SessionManager;
use Framework\Session\SessionRegistry;

/**
 * Session management with security best practices.
 *
 * Data lives in the driver chosen by SESSION_DRIVER. Every signed-in session
 * also has a user_sessions row, and the session is only trusted while that row
 * exists and has not expired, which is what lets a session be revoked from
 * another device whatever the driver.
 *
 * The cookie is sent by this class, not by PHP, and only once there is
 * something worth storing: a reader who only browses gets no stored session
 * and no cookie.
 */
class Session
{
    /** The key App\Auth keeps the signed-in user under. */
    public const USER_KEY = 'user_id';

    private bool $enabled;

    private bool $started = false;

    private bool $saved = false;

    private ?SessionDriver $driver = null;

    /** The id the browser sent, when it sent one. */
    private ?string $cookieId = null;

    /** The browser presented a session that no longer exists or has expired. */
    private bool $endedElsewhere = false;

    private ?string $clientIp = null;

    private string $userAgent = '';

    /**
     * Sessions are disabled in CLI and cache warming contexts, where nothing
     * is stored and $_SESSION is a plain array.
     *
     * @param  array<string, mixed>  $config  config/session.php
     */
    public function __construct(
        private ?SessionManager $manager = null,
        private ?SessionRegistry $registry = null,
        private array $config = [],
    ) {
        $isCli = PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg';
        $isCacheWarming = filter_var(env('CACHE_WARMING', false), FILTER_VALIDATE_BOOLEAN);

        $this->enabled = !$isCli && !$isCacheWarming && $manager !== null && $registry !== null;

        // Anything that runs before start(), such as an error page rendered
        // because start() itself failed, reads an empty session.
        if (!isset($_SESSION)) {
            $_SESSION = [];
        }
    }

    /**
     * Open the session for this request.
     *
     * Runs as a pre-routing step, after maintenance mode and health checks so
     * those still answer while the session store is down.
     */
    public function start(Request $request): void
    {
        if (!$this->enabled || $this->started) {
            return;
        }

        $this->configure();

        $this->driver = $this->manager->driver();
        session_set_save_handler($this->driver, false);

        $this->clientIp = $request->ip();
        $this->userAgent = (string) $request->header('User-Agent', '');
        $this->driver->setClientIp($this->clientIp);

        $id = $request->cookie[$this->cookieName()] ?? null;

        if (is_string($id) && preg_match('/^[A-Za-z0-9,-]{22,256}$/', $id) === 1) {
            $this->cookieId = $id;

            if ($this->driver->validateId($id)) {
                session_id($id);
            } else {
                $this->endedElsewhere = true;
            }
        }

        session_start();
        $this->started = true;

        // Covers responses that exit before index.php reaches save(), such as
        // a pre-routing redirect.
        register_shutdown_function([$this, 'save']);

        $this->checkSignedInRecord();
    }

    /**
     * Configure secure session cookie parameters and validation rules.
     */
    protected function configure(): void
    {
        session_name($this->cookieName());

        // Reject uninitialized session IDs to prevent fixation attacks
        ini_set('session.use_strict_mode', '1');

        // The id is read from the cookie and the cookie written by this class.
        ini_set('session.use_cookies', '0');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');

        // Cache middleware controls all cache headers
        ini_set('session.cache_limiter', '');

        ini_set('session.gc_maxlifetime', (string) $this->manager->longestIdleSeconds());

        // Database and Redis sessions are pruned by the scheduler and by TTLs.
        if ($this->manager->name() !== 'file') {
            ini_set('session.gc_probability', '0');
        }
    }

    /**
     * Persist the session and send the cookie if this response needs one.
     *
     * Called before the response is sent, so a storage failure is still an
     * error page rather than an exception lost after the output.
     */
    public function save(): void
    {
        if (!$this->started || $this->saved) {
            return;
        }

        $this->saved = true;
        $id = session_id();
        $known = $this->cookieId !== null && $this->cookieId === $id;

        if (!$known && $this->isDisposable()) {
            session_abort();

            if ($this->cookieId !== null) {
                $this->sendCookie('', time() - 42000);
            }

            return;
        }

        session_write_close();

        if (!$known) {
            $this->sendCookie($id, 0);
        }
    }

    /**
     * Record that this session now belongs to a user (sign-in, or the start
     * and end of impersonation). Call after regenerate().
     *
     * @param  array<string, mixed>  $record  user_sessions columns
     */
    public function bind(array $record): void
    {
        if (!$this->started) {
            return;
        }

        $this->registry->open($this->hashedId(), $record, time());
    }

    /**
     * The key of this session in user_sessions; empty when sessions are disabled.
     */
    public function hashedId(): string
    {
        return $this->started ? SessionRegistry::hash(session_id()) : '';
    }

    /**
     * True when the browser came back with a session that was revoked or ran
     * out of time, so the sign-in page can say why.
     */
    public function wasSignedOutElsewhere(): bool
    {
        return $this->endedElsewhere;
    }

    public function clientIp(): ?string
    {
        return $this->clientIp;
    }

    public function userAgent(): string
    {
        return $this->userAgent;
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
     * End the session: its data and its user_sessions row are deleted and the
     * request carries on with a fresh, empty one. The cookie is cleared when
     * the response is saved, unless something new is stored by then.
     */
    public function destroy(): void
    {
        $_SESSION = [];

        if (!$this->started) {
            return;
        }

        $this->registry->forget($this->hashedId());
        session_regenerate_id(true);
    }

    /**
     * Regenerate session ID to prevent fixation attacks.
     *
     * Call this after authentication state changes such as login, logout,
     * or privilege escalation. The user_sessions row moves to the new id.
     *
     * @param  bool  $deleteOldSession  Whether to delete the old session data
     */
    public function regenerate(bool $deleteOldSession = true): void
    {
        if (!$this->started) {
            return;
        }

        $oldId = session_id();

        // The old data is removed by hand after the row moves; letting PHP
        // destroy it first would delete the database driver's row with it.
        session_regenerate_id(false);
        $this->registry->rekey(SessionRegistry::hash($oldId), SessionRegistry::hash(session_id()));

        if ($deleteOldSession) {
            $this->driver->destroy($oldId);
        }
    }

    /**
     * Get the current session ID.
     *
     * @return string Session ID, or empty string if sessions are disabled
     */
    public function id(): string
    {
        return $this->started ? session_id() : '';
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

    /**
     * Drop a signed-in session whose user_sessions row is gone, belongs to
     * someone else, or has run out of time; otherwise record the activity.
     */
    private function checkSignedInRecord(): void
    {
        $userId = $_SESSION[self::USER_KEY] ?? null;

        if ($userId === null) {
            return;
        }

        $hash = $this->hashedId();
        $row = $this->driver->storesRecords() ? $this->driver->record(session_id()) : $this->registry->find($hash);
        $now = time();

        if ($row === null || (int) $row['user_id'] !== (int) $userId || (int) $row['expires_at'] <= $now) {
            if ($row !== null) {
                $this->registry->forget($hash);
            }

            $_SESSION = [];
            session_regenerate_id(true);
            $this->endedElsewhere = true;

            return;
        }

        // The database driver records activity in the same write as the data.
        if (!$this->driver->storesRecords()) {
            $this->registry->touch($row, $now, (int) $this->config['activity_interval_seconds'], $this->clientIp);
        }
    }

    private function isDisposable(): bool
    {
        return array_diff(array_keys($_SESSION), (array) ($this->config['disposable_keys'] ?? [])) === [];
    }

    private function cookieName(): string
    {
        return (string) ($this->config['cookie'] ?? 'PHPSESSID');
    }

    private function sendCookie(string $value, int $expires): void
    {
        if (headers_sent($file, $line)) {
            error_log("Session cookie not sent: output already started at {$file}:{$line}");

            return;
        }

        setcookie($this->cookieName(), $value, [
            'expires' => $expires,
            'path' => '/',
            'domain' => '',
            'secure' => HttpUtils::isHttps(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}
