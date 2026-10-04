<?php

declare(strict_types=1);

namespace App\Models;

/**
 * One random salt per UTC day for anonymous visitor hashes. Once a day's salt
 * is deleted, that day's hashes can't be linked to any other day.
 */
class TrafficSaltModel extends AppModel
{
    protected ?string $table = 'traffic_salts';

    /**
     * The salt for a UTC day, created on first use. INSERT IGNORE makes two
     * requests racing at midnight agree on a single salt.
     *
     * @param  string  $day  Y-m-d, UTC
     * @return string 32 raw bytes
     */
    public function saltFor(string $day): string
    {
        $salt = $this->stored($day);

        if ($salt !== null) {
            return $salt;
        }

        $this->database->execute(
            'INSERT IGNORE INTO traffic_salts (day, salt) VALUES (?, ?)',
            [$day, random_bytes(32)]
        );

        $salt = $this->stored($day);

        if ($salt === null) {
            throw new \RuntimeException("Could not create the traffic salt for {$day}.");
        }

        return $salt;
    }

    /**
     * Delete every salt before the given UTC day.
     */
    public function deleteBefore(string $day): int
    {
        return $this->database->execute('DELETE FROM traffic_salts WHERE day < ?', [$day]);
    }

    private function stored(string $day): ?string
    {
        $salt = $this->database->query('SELECT salt FROM traffic_salts WHERE day = ?', [$day])->fetchColumn();

        return is_string($salt) ? $salt : null;
    }
}
