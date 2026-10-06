<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use MaxMind\Db\Reader;
use MaxMind\Db\Reader\InvalidDatabaseException;

/**
 * A DB-IP Lite database file on this server, opened on first use. Without the
 * file every lookup comes back empty.
 */
abstract class MmdbLookup
{
    private ?Reader $reader = null;

    private bool $opened = false;

    public function __construct(private string $databasePath) {}

    public function available(): bool
    {
        return is_file($this->databasePath);
    }

    /**
     * The record for a public IP address, or null.
     *
     * @return array<string, mixed>|null
     */
    protected function record(string $ip): ?array
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
            // A damaged file should cost this column, not the page view.
            error_log(static::class.': '.$e->getMessage());

            return null;
        }

        return is_array($record) ? $record : null;
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
            error_log(static::class.': cannot open '.$this->databasePath.': '.$e->getMessage());
        }

        return $this->reader;
    }
}
