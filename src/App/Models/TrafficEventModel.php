<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\Traffic\LexiconSource;
use App\ValueObjects\TrafficScope;

/**
 * Sign-ups, goals and link clicks from counted visits, with nothing that points
 * to a visitor or an account.
 */
class TrafficEventModel extends AppModel
{
    protected ?string $table = 'traffic_events';

    /** Things a reader can do on a blog that its owner may want more of. */
    public const GOALS = ['subscribe', 'comment', 'like', 'save'];

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

    /**
     * @param  string  $day  Y-m-d in the blog's timezone, like the blog's other numbers
     */
    public function recordGoal(string $goal, int $blogId, ?int $postId, string $day): void
    {
        if (!in_array($goal, self::GOALS, true)) {
            throw new \InvalidArgumentException("Unknown traffic goal '{$goal}'.");
        }

        $this->database->execute(
            'INSERT INTO traffic_events (event, day, blog_id, post_id) VALUES (?, ?, ?, ?)',
            [$goal, $day, $blogId, $postId]
        );
    }

    /**
     * @param  string  $kind  outbound or download
     * @param  string  $target  The other site's host, or the file name
     */
    public function recordClick(string $kind, ?int $blogId, ?int $postId, string $target, string $day): void
    {
        $this->database->execute(
            'INSERT INTO traffic_events (event, day, blog_id, post_id, value) VALUES (?, ?, ?, ?, ?)',
            [$kind, $day, $blogId, $postId, $target]
        );
    }

    /**
     * How often each goal was reached in the scope. A blog also counts the sign-ups
     * of readers whose last page before signing up was on it; the site counts all.
     *
     * @return array<string, int> Goal or 'signup' => count, every goal present
     */
    public function goalCounts(TrafficScope $scope, string $from, string $to): array
    {
        $counts = array_fill_keys([...self::GOALS, 'signup'], 0);
        [$where, $params] = $this->scopeClause($scope);
        $goals = "'".implode("', '", self::GOALS)."'";

        $rows = $this->database->query(
            "SELECT event, COUNT(*) AS reached FROM traffic_events
             WHERE event IN ({$goals}) AND {$where} AND day BETWEEN ? AND ?
             GROUP BY event",
            [...$params, $from, $to]
        )->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($rows as $row) {
            $counts[(string) $row['event']] = (int) $row['reached'];
        }

        $counts['signup'] = match ($scope->type) {
            TrafficScope::SITE => $this->countSignups(null, $from, $to),
            TrafficScope::BLOG => $this->countSignups(LexiconSource::blog((int) $scope->blogId), $from, $to),
            default => 0,
        };

        return $counts;
    }

    /**
     * Outbound hosts or downloaded files in the scope, most clicked first.
     *
     * @return list<array{value: string, clicks: int}>
     */
    public function clickBreakdown(string $kind, TrafficScope $scope, string $from, string $to, int $limit): array
    {
        if (!in_array($kind, ['outbound', 'download'], true)) {
            throw new \InvalidArgumentException("Unknown click kind '{$kind}'.");
        }

        [$where, $params] = $this->scopeClause($scope);

        $rows = $this->database->query(
            "SELECT value, COUNT(*) AS clicks FROM traffic_events
             WHERE event = ? AND {$where} AND day BETWEEN ? AND ?
             GROUP BY value
             ORDER BY clicks DESC, value ASC
             LIMIT ".max(1, $limit),
            [$kind, ...$params, $from, $to]
        )->fetchAll(\PDO::FETCH_ASSOC);

        return array_map(static fn (array $row): array => [
            'value' => (string) $row['value'],
            'clicks' => (int) $row['clicks'],
        ], $rows);
    }

    private function countSignups(?string $cameFrom, string $from, string $to): int
    {
        $sql = "SELECT COUNT(*) FROM traffic_events WHERE event = 'signup' AND day BETWEEN ? AND ?";
        $params = [$from, $to];

        if ($cameFrom !== null) {
            $sql .= ' AND came_from = ?';
            $params[] = $cameFrom;
        }

        return (int) $this->database->query($sql, $params)->fetchColumn();
    }

    /**
     * @return array{0: string, 1: list<int>}
     */
    private function scopeClause(TrafficScope $scope): array
    {
        if ($scope->type === TrafficScope::SITE) {
            return ['TRUE', []];
        }

        if ($scope->type === TrafficScope::PLATFORM) {
            return ['blog_id IS NULL', []];
        }

        if ($scope->type === TrafficScope::BLOG) {
            return ['blog_id = ?', [(int) $scope->blogId]];
        }

        if ($scope->ids === []) {
            return ['1 = 0', []];
        }

        $placeholders = implode(', ', array_fill(0, count($scope->ids), '?'));

        return ["blog_id = ? AND post_id IN ({$placeholders})", [(int) $scope->blogId, ...$scope->ids]];
    }
}
