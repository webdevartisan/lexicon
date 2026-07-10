<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Editable site text stored as name/locale/value rows.
 *
 * The locale files remain the defaults; this table only holds admin
 * overrides. An empty locale string marks a value that applies to every
 * locale (URLs, email addresses, phone numbers). Rows are cached in memory
 * for the duration of the request, mirroring SettingModel.
 */
class SiteContentModel extends AppModel
{
    protected ?string $table = 'site_content';

    /**
     * Request-level cache keyed by locale then name.
     *
     * @var array<string, array<string, string>>|null
     */
    private ?array $cache = null;

    /**
     * Load every override grouped by locale.
     *
     * @return array<string, array<string, string>> locale => [name => value]
     */
    public function all(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $sql = "SELECT name, locale, value FROM {$this->getTable()}";
        $rows = $this->database->query($sql)->fetchAll(\PDO::FETCH_ASSOC);

        $this->cache = [];
        foreach ($rows as $row) {
            $this->cache[$row['locale']][$row['name']] = $row['value'];
        }

        return $this->cache;
    }

    /**
     * Effective override for a key: locale-specific first, then locale-independent.
     *
     * @param  string  $name  Content key, e.g. 'front.hero.title'
     * @param  string  $locale  Current locale code
     * @return string|null The override value, or null when none exists
     */
    public function get(string $name, string $locale): ?string
    {
        $all = $this->all();

        return $all[$locale][$name] ?? $all[''][$name] ?? null;
    }

    /**
     * Raw override for one exact name and locale, without fallback.
     *
     * Used by the admin form so each locale tab shows only its own values.
     */
    public function getExact(string $name, string $locale): ?string
    {
        $all = $this->all();

        return $all[$locale][$name] ?? null;
    }

    /**
     * Upsert a batch of overrides for one locale inside a transaction.
     *
     * Empty values delete the row so the text falls back to the locale file
     * default instead of storing blank strings.
     *
     * @param  array<string, string>  $values  name => value
     * @param  string  $locale  Locale code, or '' for locale-independent values
     * @return bool True when the batch committed
     */
    public function setMany(array $values, string $locale): bool
    {
        if ($values === []) {
            return true;
        }

        return $this->transaction(function () use ($values, $locale) {
            $upsert = "INSERT INTO {$this->getTable()} (name, locale, value)
                       VALUES (?, ?, ?)
                       ON DUPLICATE KEY UPDATE value = ?";
            $delete = "DELETE FROM {$this->getTable()} WHERE name = ? AND locale = ?";

            foreach ($values as $name => $value) {
                $value = trim((string) $value);

                if ($value === '') {
                    $this->database->execute($delete, [$name, $locale]);
                    continue;
                }

                $this->database->execute($upsert, [$name, $locale, $value, $value]);
            }

            $this->cache = null;

            return true;
        });
    }
}
