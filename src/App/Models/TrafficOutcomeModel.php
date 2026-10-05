<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Per day, how many page view beacons were counted and how many each check turned away.
 */
class TrafficOutcomeModel extends AppModel
{
    protected ?string $table = 'traffic_outcomes';

    public function increment(string $outcome): void
    {
        $this->database->execute(
            'INSERT INTO traffic_outcomes (day, outcome, requests) VALUES (UTC_DATE(), ?, 1)
             ON DUPLICATE KEY UPDATE requests = requests + 1',
            [$outcome]
        );
    }

    /**
     * @return array<string, int> Outcome => requests, most first
     */
    public function totals(string $from, string $to): array
    {
        $rows = $this->database->query(
            'SELECT outcome, SUM(requests) AS requests FROM traffic_outcomes
             WHERE day BETWEEN ? AND ?
             GROUP BY outcome
             ORDER BY requests DESC, outcome ASC',
            [$from, $to]
        )->fetchAll(\PDO::FETCH_ASSOC);

        $totals = [];
        foreach ($rows as $row) {
            $totals[(string) $row['outcome']] = (int) $row['requests'];
        }

        return $totals;
    }
}
