<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Single source of truth for maintenance mode.
 *
 * State lives in a flag file (storage/maintenance.json), never in the
 * database: during upgrades or incidents the database may be unreachable,
 * and that is exactly when maintenance mode must still work. The file is
 * written by the admin Settings toggle, and deploy scripts can create or
 * delete it directly:
 *
 *     echo {} > storage/maintenance.json   # site down
 *     rm storage/maintenance.json          # site up
 *
 * The payload is self-contained (allowlist, retry-after, site name are
 * resolved at enable time) so the pre-routing gate needs nothing but this
 * file to decide.
 */
class MaintenanceMode
{
    /**
     * Absolute path of the maintenance flag file.
     */
    public static function filePath(): string
    {
        return ROOT_PATH.'/storage/maintenance.json';
    }

    /**
     * Whether maintenance mode is currently on.
     */
    public static function active(): bool
    {
        return is_file(self::filePath());
    }

    /**
     * Read the flag file payload.
     *
     * Returns an empty array when maintenance is off or the file is not
     * valid JSON (a bare `touch` of the file is a valid way to enable).
     *
     * @return array{enabled_at?: string, allow?: string[], retry_after?: int, site_name?: string}
     */
    public static function payload(): array
    {
        if (!self::active()) {
            return [];
        }

        $raw = @file_get_contents(self::filePath());
        if ($raw === false) {
            return [];
        }

        // Editors and Windows shells often prepend a UTF-8 BOM, which would
        // make json_decode fail and silently drop the payload
        $raw = trim(preg_replace('/^\xEF\xBB\xBF/', '', $raw));
        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Turn maintenance mode on.
     *
     * Snapshots the allowlist and site name into the payload so the gate
     * never needs the environment or database while the site is down.
     *
     * @param  array{allow?: string[], retry_after?: int, site_name?: string}  $options  Overrides for the payload
     * @return bool True when the flag file was written
     */
    public function enable(array $options = []): bool
    {
        $payload = [
            'enabled_at' => date('c'),
            'allow' => $options['allow'] ?? self::configuredAllowlist(),
            'retry_after' => (int) ($options['retry_after'] ?? env('MAINTENANCE_RETRY_AFTER', 600)),
            'site_name' => (string) ($options['site_name'] ?? 'Lexicon'),
        ];

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        return file_put_contents(self::filePath(), $json, LOCK_EX) !== false;
    }

    /**
     * Turn maintenance mode off.
     *
     * @return bool True when the site is up afterwards (file gone)
     */
    public function disable(): bool
    {
        if (!self::active()) {
            return true;
        }

        return @unlink(self::filePath());
    }

    /**
     * IPs and CIDR blocks that bypass maintenance, from MAINTENANCE_ALLOW.
     *
     * @return string[]
     */
    public static function configuredAllowlist(): array
    {
        $raw = (string) env('MAINTENANCE_ALLOW', '');

        return array_values(array_filter(array_map(
            static fn (string $entry): string => trim($entry, " \t\n\r\0\x0B\"'"),
            explode(',', $raw)
        )));
    }
}
