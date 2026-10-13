<?php

declare(strict_types=1);

namespace App\Presenters;

use App\Services\Sessions\SessionLocation;

/**
 * Turns user_sessions rows into what a session list shows: which device, in
 * which language, from roughly where, and when.
 *
 * Used by the account security page and the admin user page, so the two can
 * never describe the same session differently.
 */
class SessionListPresenter
{
    /** Activity this recent reads as "active now" rather than a timestamp. */
    private const ACTIVE_NOW_SECONDS = 300;

    /**
     * @param  callable(string, array<string, mixed>=): string  $t  Translator for the page's language
     */
    public function __construct(
        private $t,
        private string $locale,
        private string $timezone,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $rows  From SessionRegistry::forUser()
     * @param  string  $currentHash  The viewer's own session, listed first
     * @param  bool  $truncateIp  Administrators see the network, not the address
     * @return list<array<string, mixed>>
     */
    public function present(array $rows, string $currentHash, bool $truncateIp = false): array
    {
        $now = time();
        $items = [];

        foreach ($rows as $row) {
            $ip = $row['ip_address'] !== null ? (string) $row['ip_address'] : null;
            $lastActivity = (int) $row['last_activity'];

            $items[] = [
                'hash' => (string) $row['id'],
                'current' => $currentHash !== '' && hash_equals((string) $row['id'], $currentHash),
                'device' => in_array($row['device'], ['desktop', 'mobile', 'tablet'], true) ? $row['device'] : 'other',
                'client' => $this->client($row),
                'place' => $this->place($row, $ip),
                'ip' => $ip !== null && $truncateIp ? self::network($ip) : $ip,
                'signed_in' => $this->date((int) ($row['signed_in_at'] ?? $row['created_at'])),
                'active_now' => $now - $lastActivity < self::ACTIVE_NOW_SECONDS,
                'last_active' => $this->date($lastActivity),
                'impersonated_by' => $row['impersonated_by'] !== null ? (int) $row['impersonated_by'] : null,
            ];
        }

        // This device first, the rest by most recent activity as the query returned them.
        usort($items, static fn (array $a, array $b): int => $b['current'] <=> $a['current']);

        return $items;
    }

    /**
     * "Chrome 131 on Windows", in the page's language.
     *
     * @param  array<string, mixed>  $row
     */
    private function client(array $row): string
    {
        $browser = self::named($row['browser'] ?? null, $row['browser_version'] ?? null);
        $os = self::named($row['os'] ?? null, $row['os_version'] ?? null);

        if ($browser === null && $os === null) {
            return ($this->t)('account.sessions.unknownDevice');
        }

        if ($os === null) {
            return $browser;
        }

        if ($browser === null) {
            return $os;
        }

        return ($this->t)('account.sessions.clientOn', ['browser' => $browser, 'os' => $os]);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function place(array $row, ?string $ip): ?string
    {
        if (SessionLocation::isLocal($ip)) {
            return ($this->t)('account.sessions.localNetwork');
        }

        $country = $row['country'] !== null ? \Locale::getDisplayRegion('-'.$row['country'], $this->locale) : null;
        $city = $row['city'] ?? null;

        if ($country === null || $country === '') {
            return $city;
        }

        return $city !== null ? $city.', '.$country : $country;
    }

    private function date(int $timestamp): string
    {
        $formatter = new \IntlDateFormatter(
            $this->locale,
            \IntlDateFormatter::MEDIUM,
            \IntlDateFormatter::SHORT,
            $this->timezone
        );

        return (string) $formatter->format($timestamp);
    }

    private static function named(?string $name, ?string $version): ?string
    {
        if ($name === null || $name === '' || $name === 'Other') {
            return null;
        }

        return $version !== null && $version !== '' ? $name.' '.$version : $name;
    }

    /**
     * The /24 of an IPv4 address or the /48 of an IPv6 one.
     */
    public static function network(string $ip): string
    {
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return $ip;
        }

        $packed = (string) inet_pton($ip);

        if (strlen($packed) === 4) {
            return (string) inet_ntop(substr($packed, 0, 3)."\0").'/24';
        }

        return (string) inet_ntop(substr($packed, 0, 6).str_repeat("\0", 10)).'/48';
    }
}
