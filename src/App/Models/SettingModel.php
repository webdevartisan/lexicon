<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Manage site-wide configuration settings stored as key-value pairs.
 *
 * We use a simple key-value table to store settings that can be updated
 * from the admin panel without requiring code deployments. Settings are
 * cached in memory for the duration of the request to minimize database queries.
 */
class SettingModel extends AppModel
{
    /**
     * The database table name.
     *
     * We store general website settings (not blog-specific).
     */
    protected ?string $table = 'settings';

    /**
     * In-memory cache of all settings for this request.
     *
     * We populate this on the first call to all() or get() and reuse it
     * throughout the request to avoid repeated database queries.
     *
     * @var array<string, string>|null
     */
    private ?array $cache = null;

    /**
     * Get all settings as a key-value array.
     *
     * We cache the result in memory for the duration of the request to avoid
     * repeated queries. The cache is invalidated when set() or setMany() is called.
     *
     * @return array<string, string> Associative array of setting_name => value
     */
    public function all(): array
    {
        // return cached settings if already loaded
        if ($this->cache !== null) {
            return $this->cache;
        }

        $sql = "SELECT name, value FROM {$this->getTable()}";
        $stmt = $this->database->query($sql);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // transform rows into a simple key-value array
        $this->cache = [];
        foreach ($rows as $row) {
            $this->cache[$row['name']] = $row['value'];
        }

        return $this->cache;
    }

    /**
     * Get a single setting by name.
     *
     * We load all settings on the first get() call and serve from cache
     * for subsequent calls. This is more efficient than individual queries
     * when multiple settings are needed in the same request.
     *
     * @param  string  $name  Setting name (e.g., 'site_name', 'posts_per_page')
     * @param  string|null  $default  Default value if setting doesn't exist
     * @return string|null Setting value or default
     */
    public function get(string $name, ?string $default = null): ?string
    {
        // load all settings into cache if not already loaded
        if ($this->cache === null) {
            $this->all();
        }

        return $this->cache[$name] ?? $default;
    }

    /**
     * Set or update a single setting.
     *
     * We use INSERT ... ON DUPLICATE KEY UPDATE for upsert behavior.
     * This works if 'name' column has a UNIQUE constraint.
     *
     * @param  string  $name  Setting name
     * @param  string  $value  Setting value
     * @return bool True on success, false on failure
     */
    public function set(string $name, string $value): bool
    {
        $sql = "INSERT INTO {$this->getTable()} (name, value)
                VALUES (?, ?)
                ON DUPLICATE KEY UPDATE value = ?";

        // provide the value parameter twice: once for INSERT, once for UPDATE
        $rowCount = $this->database->execute($sql, [$name, $value, $value]);

        // invalidate cache after updating so next get() fetches fresh data
        if ($rowCount >= 0) {
            $this->cache = null;

            return true;
        }

        return false;
    }

    /**
     * Set multiple settings in a single transaction.
     *
     * We batch updates for better performance when saving the settings form.
     * All changes are committed together or rolled back on failure.
     *
     * @param  array<string, string>  $settings  Associative array of name => value
     * @return bool True if all settings were saved successfully
     */
    public function setMany(array $settings): bool
    {
        if (empty($settings)) {
            return true;
        }

        return $this->transaction(function () use ($settings) {
            $sql = "INSERT INTO {$this->getTable()} (name, value)
                    VALUES (?, ?)
                    ON DUPLICATE KEY UPDATE value = ?";

            // execute the same query for each setting with different parameters
            foreach ($settings as $name => $value) {
                // provide value twice: once for INSERT, once for UPDATE
                $this->database->execute($sql, [$name, (string) $value, (string) $value]);
            }

            // invalidate cache after batch update
            $this->cache = null;

            return true;
        });
    }

    /**
     * Get a setting as an integer.
     *
     * We provide type-safe getters for common setting types to reduce
     * casting logic in controllers and views.
     *
     * @param  string  $name  Setting name
     * @param  int  $default  Default value if not found or not numeric
     * @return int Setting value as integer or default
     */
    public function getInt(string $name, int $default = 0): int
    {
        $value = $this->get($name);

        if ($value === null) {
            return $default;
        }

        return is_numeric($value) ? (int) $value : $default;
    }

    /**
     * Get a setting as a boolean.
     *
     * We treat '1', 'true', 'yes', 'on' as true (case-insensitive).
     * Everything else (including '0', 'false', 'no', 'off', null) is false.
     *
     * @param  string  $name  Setting name
     * @param  bool  $default  Default value if not found
     * @return bool Setting value as boolean or default
     */
    public function getBool(string $name, bool $default = false): bool
    {
        $value = $this->get($name);

        if ($value === null) {
            return $default;
        }

        return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
    }
}
