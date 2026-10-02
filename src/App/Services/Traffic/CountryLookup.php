<?php

declare(strict_types=1);

namespace App\Services\Traffic;

use MaxMind\Db\Reader;
use MaxMind\Db\Reader\InvalidDatabaseException;

/**
 * Country for an IP address, looked up in the DB-IP Lite file on this server.
 */
class CountryLookup
{
    private ?Reader $reader = null;

    private bool $opened = false;

    public function __construct(private string $databasePath) {}

    public function available(): bool
    {
        return is_file($this->databasePath);
    }

    /**
     * @return string|null Upper-case ISO 3166 alpha-2 code
     */
    public function country(string $ip): ?string
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return null;
        }

        $reader = $this->reader();

        if ($reader === null) {
            return null;
        }

        try {
            $record = $reader->get($ip);
        } catch (InvalidDatabaseException $e) {
            // A damaged file should cost the country column, not the page view.
            error_log('CountryLookup: '.$e->getMessage());

            return null;
        }

        $code = is_array($record) ? ($record['country']['iso_code'] ?? null) : null;

        return is_string($code) && preg_match('/^[A-Z]{2}$/', $code) ? $code : null;
    }

    private function reader(): ?Reader
    {
        if ($this->opened) {
            return $this->reader;
        }

        $this->opened = true;

        if (!$this->available()) {
            return null;
        }

        try {
            $this->reader = new Reader($this->databasePath);
        } catch (InvalidDatabaseException $e) {
            error_log('CountryLookup: cannot open '.$this->databasePath.': '.$e->getMessage());
        }

        return $this->reader;
    }
}
