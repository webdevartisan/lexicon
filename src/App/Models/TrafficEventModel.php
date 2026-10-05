<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Sign-ups from counted visits, each with the visit's sources and nothing that
 * points to a visitor or an account.
 */
class TrafficEventModel extends AppModel
{
    protected ?string $table = 'traffic_events';

    /**
     * Breakdown => column. Fixed strings, never input.
     */
    private const BREAKDOWNS = [
        'channel' => 'channel',
        'source' => 'referrer_source',
        'came_from' => 'came_from',
        'utm_campaign' => 'utm_campaign',
    ];

    /**
     * @param  array<string, string|null>  $sources  channel, referrer_source, utm_source, utm_medium, utm_campaign, came_from
     */
    public function recordSignup(array $sources): void
    {
        $columns = array_keys($sources);
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));

        $this->database->execute(
            'INSERT INTO traffic_events (event, day, '.implode(', ', $columns).")
             VALUES ('signup', UTC_DATE(), {$placeholders})",
            array_values($sources)
        );
    }

    /**
     * @return list<string>
     */
    public static function breakdowns(): array
    {
        return array_keys(self::BREAKDOWNS);
    }

    /**
     * @return list<array{value: string, signups: int}>
     */
    public function signupBreakdown(string $dimension, string $from, string $to, int $limit): array
    {
        $column = self::BREAKDOWNS[$dimension] ?? throw new \InvalidArgumentException("Unknown sign-up breakdown '{$dimension}'.");

        $rows = $this->database->query(
            "SELECT {$column} AS value, COUNT(*) AS signups
             FROM traffic_events
             WHERE event = 'signup' AND day BETWEEN ? AND ? AND {$column} IS NOT NULL
             GROUP BY {$column}
             ORDER BY signups DESC, value ASC
             LIMIT ".max(1, $limit),
            [$from, $to]
        )->fetchAll(\PDO::FETCH_ASSOC);

        return array_map(static fn (array $row): array => [
            'value' => (string) $row['value'],
            'signups' => (int) $row['signups'],
        ], $rows);
    }
}
