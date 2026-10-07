<?php

declare(strict_types=1);

namespace App\Models;

use App\ValueObjects\AnalyticsScope;

/**
 * Goals, clicks and sign-ups per day, read from analytics_daily_events, which
 * keeps them after the raw events are pruned.
 *
 * Goals and clicks are stored per blog, per post and for the platform's own
 * pages; the site adds those up, so a deleted blog's rows leave the site's
 * totals with it. Sign-ups belong to the site and, through came_from, to the
 * blog the reader was on.
 */
class AnalyticsDailyEventModel extends AppModel
{
    protected ?string $table = 'analytics_daily_events';

    /** Things a reader can do on a blog that its owner may want more of. */
    public const GOALS = ['subscribe', 'comment', 'like', 'save', 'share'];

    private const CLICKS = ['outbound' => 'host', 'download' => 'file', 'share' => 'network'];

    /** Sign-up lists on the Sign-ups page. */
    private const SIGNUP_BREAKDOWNS = ['channel', 'source', 'came_from', 'utm_campaign'];

    /**
     * @return list<string>
     */
    public static function breakdowns(): array
    {
        return self::SIGNUP_BREAKDOWNS;
    }

    /**
     * @return list<array{value: string, signups: int}>
     */
    public function signupBreakdown(string $dimension, string $from, string $to, int $limit): array
    {
        if (!in_array($dimension, self::SIGNUP_BREAKDOWNS, true)) {
            throw new \InvalidArgumentException("Unknown sign-up breakdown '{$dimension}'.");
        }

        $rows = $this->database->query(
            "SELECT value, SUM(events) AS signups FROM analytics_daily_events
             WHERE scope = 'site' AND name = 'signup' AND breakdown = ? AND day BETWEEN ? AND ?
             GROUP BY value
             ORDER BY signups DESC, value ASC
             LIMIT ".max(1, $limit),
            [$dimension, $from, $to]
        )->fetchAll(\PDO::FETCH_ASSOC);

        return array_map(static fn (array $row): array => [
            'value' => (string) $row['value'],
            'signups' => (int) $row['signups'],
        ], $rows);
    }

    /**
     * How often each goal was reached in the scope. A blog also counts the sign-ups
     * of readers whose last page before signing up was on it; the site counts all.
     *
     * @return array<string, int> Goal or 'signup' => count, every goal present
     */
    public function goalCounts(AnalyticsScope $scope, string $from, string $to): array
    {
        $counts = array_fill_keys([...self::GOALS, 'signup'], 0);
        [$where, $params] = $this->scopeClause($scope);
        $goals = "'".implode("', '", self::GOALS)."'";

        $rows = $this->database->query(
            "SELECT name, SUM(events) AS reached FROM analytics_daily_events
             WHERE name IN ({$goals}) AND breakdown = '' AND {$where} AND day BETWEEN ? AND ?
             GROUP BY name",
            [...$params, $from, $to]
        )->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($rows as $row) {
            $counts[(string) $row['name']] = (int) $row['reached'];
        }

        $counts['signup'] = $this->signups($scope, $from, $to);

        return $counts;
    }

    /**
     * Everything the Goals page reads from the daily rows, in one pass instead of six scans.
     * The window function keeps each click list to its top $limit inside SQL, so a site with
     * thousands of outbound hosts still gets a short result.
     *
     * @return array{
     *     goals: array<string, int>,
     *     byChannel: array<string, array<string, int>>,
     *     bySource: array<string, array<string, int>>,
     *     outbound: list<array{value: string, clicks: int}>,
     *     downloads: list<array{value: string, clicks: int}>,
     *     shares: list<array{value: string, clicks: int}>
     * }
     */
    public function goalsReport(AnalyticsScope $scope, string $from, string $to, int $limit): array
    {
        [$where, $params] = $this->scopeClause($scope);
        $names = array_values(array_unique([...self::GOALS, ...array_keys(self::CLICKS)]));
        $marks = implode(', ', array_fill(0, count($names), '?'));

        $rows = $this->database->query(
            "SELECT r.name, r.breakdown, r.value, r.total FROM (
                 SELECT name, breakdown, value, SUM(events) AS total,
                        ROW_NUMBER() OVER (PARTITION BY name, breakdown ORDER BY SUM(events) DESC, value ASC) AS ranked
                 FROM analytics_daily_events
                 WHERE name IN ({$marks}) AND breakdown IN ('', 'channel', 'source', 'host', 'file', 'network')
                   AND {$where} AND day BETWEEN ? AND ?
                 GROUP BY name, breakdown, value
             ) r
             WHERE r.breakdown IN ('', 'channel', 'source') OR r.ranked <= ?
             ORDER BY r.name, r.breakdown, r.ranked",
            [...$names, ...$params, $from, $to, max(1, $limit)]
        )->fetchAll(\PDO::FETCH_ASSOC);

        $goals = array_fill_keys([...self::GOALS, 'signup'], 0);
        $byChannel = [];
        $bySource = [];
        $clicks = array_fill_keys(array_keys(self::CLICKS), []);

        foreach ($rows as $row) {
            $name = (string) $row['name'];
            $breakdown = (string) $row['breakdown'];
            $value = (string) $row['value'];
            $total = (int) $row['total'];

            if (in_array($name, self::GOALS, true)) {
                match ($breakdown) {
                    '' => $goals[$name] = $total,
                    'channel' => $byChannel[$value][$name] = $total,
                    'source' => $bySource[$value][$name] = $total,
                    default => null,
                };
            }

            if ((self::CLICKS[$name] ?? null) === $breakdown) {
                $clicks[$name][] = ['value' => $value, 'clicks' => $total];
            }
        }

        $goals['signup'] = $this->signups($scope, $from, $to);

        return [
            'goals' => $goals,
            'byChannel' => self::busiest($byChannel, $limit),
            'bySource' => self::busiest($bySource, $limit),
            'outbound' => $clicks['outbound'],
            'downloads' => $clicks['download'],
            'shares' => $clicks['share'],
        ];
    }

    /**
     * Goals split by how the visit began, for "goals by source". Goals reached
     * before visits were kept have no channel or source, so they are not here.
     *
     * @return array<string, array<string, int>> Value => goal => count, busiest value first
     */
    public function goalBreakdown(AnalyticsScope $scope, string $breakdown, string $from, string $to, int $limit): array
    {
        if (!in_array($breakdown, ['channel', 'source'], true)) {
            throw new \InvalidArgumentException("Goals can't be split by '{$breakdown}'.");
        }

        [$where, $params] = $this->scopeClause($scope);
        $goals = "'".implode("', '", self::GOALS)."'";

        $rows = $this->database->query(
            "SELECT value, name, SUM(events) AS reached FROM analytics_daily_events
             WHERE name IN ({$goals}) AND breakdown = ? AND {$where} AND day BETWEEN ? AND ?
             GROUP BY value, name",
            [$breakdown, ...$params, $from, $to]
        )->fetchAll(\PDO::FETCH_ASSOC);

        $byValue = [];
        foreach ($rows as $row) {
            $byValue[(string) $row['value']][(string) $row['name']] = (int) $row['reached'];
        }

        return self::busiest($byValue, $limit);
    }

    /**
     * @param  array<string, array<string, int>>  $byValue  Value => goal => count
     * @return array<string, array<string, int>> The $limit values with the most goals, busiest first
     */
    private static function busiest(array $byValue, int $limit): array
    {
        // Stable sort: equal totals end up alphabetical, not in MySQL's order.
        ksort($byValue);
        uasort($byValue, static fn (array $a, array $b): int => array_sum($b) <=> array_sum($a));

        return array_slice($byValue, 0, max(1, $limit), true);
    }

    /**
     * Goals reached on each author's posts, on one blog or across the site.
     *
     * @return array<int, int> Author id => goals
     */
    public function goalsByAuthor(?int $blogId, string $from, string $to): array
    {
        [$blogWhere, $params] = $blogId === null ? ['', []] : [' AND e.blog_id = ?', [$blogId]];
        $goals = "'".implode("', '", self::GOALS)."'";

        $rows = $this->database->query(
            "SELECT p.author_id, SUM(e.events) AS reached
             FROM analytics_daily_events e
             JOIN posts p ON p.id = e.scope_id
             WHERE e.scope = 'post' AND e.breakdown = '' AND e.name IN ({$goals}) AND e.day BETWEEN ? AND ?{$blogWhere}
             GROUP BY p.author_id",
            [$from, $to, ...$params]
        )->fetchAll(\PDO::FETCH_KEY_PAIR);

        return array_map('intval', $rows);
    }

    /**
     * How many times each of these events happened in the scope, whether or not it is a goal.
     *
     * @param  list<string>  $names
     * @return array<string, int> Name => count, 0 for none
     */
    public function eventTotals(AnalyticsScope $scope, array $names, string $from, string $to): array
    {
        $totals = array_fill_keys($names, 0);
        if ($names === []) {
            return $totals;
        }

        [$where, $params] = $this->scopeClause($scope);
        $placeholders = implode(', ', array_fill(0, count($names), '?'));

        $rows = $this->database->query(
            "SELECT name, SUM(events) AS total FROM analytics_daily_events
             WHERE name IN ({$placeholders}) AND breakdown = '' AND {$where} AND day BETWEEN ? AND ?
             GROUP BY name",
            [...$names, ...$params, $from, $to]
        )->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($rows as $row) {
            $totals[(string) $row['name']] = (int) $row['total'];
        }

        return $totals;
    }

    /**
     * Outbound hosts, downloaded files or share networks in the scope, most used first.
     *
     * @return list<array{value: string, clicks: int}>
     */
    public function clickBreakdown(string $kind, AnalyticsScope $scope, string $from, string $to, int $limit): array
    {
        $breakdown = self::CLICKS[$kind] ?? throw new \InvalidArgumentException("Unknown click kind '{$kind}'.");

        [$where, $params] = $this->scopeClause($scope);

        $rows = $this->database->query(
            "SELECT value, SUM(events) AS clicks FROM analytics_daily_events
             WHERE name = ? AND breakdown = ? AND {$where} AND day BETWEEN ? AND ?
             GROUP BY value
             ORDER BY clicks DESC, value ASC
             LIMIT ".max(1, $limit),
            [$kind, $breakdown, ...$params, $from, $to]
        )->fetchAll(\PDO::FETCH_ASSOC);

        return array_map(static fn (array $row): array => [
            'value' => (string) $row['value'],
            'clicks' => (int) $row['clicks'],
        ], $rows);
    }

    /**
     * Sign-ups the scope counts: the whole site's, or those that began on one blog.
     */
    private function signups(AnalyticsScope $scope, string $from, string $to): int
    {
        return match ($scope->type) {
            AnalyticsScope::SITE => $this->countSignups("scope = 'site'", [], $from, $to),
            AnalyticsScope::BLOG => $this->countSignups("scope = 'blog' AND scope_id = ?", [(int) $scope->blogId], $from, $to),
            default => 0,
        };
    }

    /**
     * @param  list<int>  $params
     */
    private function countSignups(string $where, array $params, string $from, string $to): int
    {
        return (int) $this->database->query(
            "SELECT COALESCE(SUM(events), 0) FROM analytics_daily_events
             WHERE name = 'signup' AND breakdown = '' AND {$where} AND day BETWEEN ? AND ?",
            [...$params, $from, $to]
        )->fetchColumn();
    }

    /**
     * Rows for goals and clicks in the scope. The site is every blog plus the platform.
     *
     * @return array{0: string, 1: list<int>}
     */
    private function scopeClause(AnalyticsScope $scope): array
    {
        if ($scope->type === AnalyticsScope::SITE) {
            return ["scope IN ('blog', 'platform')", []];
        }

        if ($scope->type === AnalyticsScope::PLATFORM) {
            return ["scope = 'platform'", []];
        }

        if ($scope->type === AnalyticsScope::BLOG) {
            return ["scope = 'blog' AND scope_id = ?", [(int) $scope->blogId]];
        }

        if ($scope->ids === []) {
            return ['1 = 0', []];
        }

        $placeholders = implode(', ', array_fill(0, count($scope->ids), '?'));

        return ["scope = 'post' AND blog_id = ? AND scope_id IN ({$placeholders})", [(int) $scope->blogId, ...$scope->ids]];
    }
}
