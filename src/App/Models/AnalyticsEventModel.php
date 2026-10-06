<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Raw events: page views and every other counted action, one row each.
 */
class AnalyticsEventModel extends AppModel
{
    protected ?string $table = 'analytics_events';

    private const PRUNE_BATCH = 5000;

    /**
     * Whether this visitor already viewed this page inside the dedupe window.
     */
    public function seenRecently(string $visitorHash, string $pathHash, int $minutes): bool
    {
        $sql = "SELECT 1 FROM analytics_events
                WHERE visitor_hash = ? AND path_hash = ? AND name = 'page_view'
                  AND created_at > UTC_TIMESTAMP() - INTERVAL ? MINUTE
                LIMIT 1";

        return (bool) $this->database->query($sql, [$visitorHash, $pathHash, $minutes])->fetchColumn();
    }

    /**
     * Store one event. The same event key twice on one day is ignored rather than
     * counted twice: a replayed page view, a like switched on again in one visit.
     *
     * @param  array<string, mixed>  $event  Column => value, keys and hashes as raw bytes, props as an array
     * @return bool False when the event was already stored
     */
    public function record(array $event): bool
    {
        if (isset($event['props'])) {
            $event['props'] = $event['props'] === [] ? null : json_encode($event['props'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        }

        $columns = array_keys($event);
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));

        $sql = 'INSERT IGNORE INTO analytics_events ('.implode(', ', $columns).', created_at)
                VALUES ('.$placeholders.', UTC_TIMESTAMP())';

        return $this->database->execute($sql, array_values($event)) > 0;
    }

    /**
     * Store an event the server saw, in the visit it happened in. It takes the
     * visit's ids and how the visit began. With $oncePerVisit, the same event on
     * the same thing counts once a visit; without a visit it always counts.
     *
     * @param  array<string, mixed>  $event  As for record(), without the visit's columns
     * @param  array<string, mixed>|null  $visit  An analytics_visits row
     */
    public function recordInVisit(array $event, ?array $visit, bool $oncePerVisit): bool
    {
        if ($visit === null) {
            return $this->record(['event_key' => random_bytes(16)] + $event);
        }

        $key = $oncePerVisit
            ? md5($visit['id'].'|'.$event['name'].'|'.($event['blog_id'] ?? '').'|'.($event['post_id'] ?? ''), true)
            : random_bytes(16);

        return $this->record($event + [
            'event_key' => $key,
            'visit_id' => $visit['id'],
            'visitor_hash' => $visit['visitor_hash'],
            'visitor_kind' => $visit['visitor_kind'],
            'channel' => $visit['channel'],
            'referrer_host' => $visit['referrer_host'],
            'referrer_source' => $visit['referrer_source'],
            'utm_source' => $visit['utm_source'],
            'utm_medium' => $visit['utm_medium'],
            'utm_campaign' => $visit['utm_campaign'],
        ]);
    }

    /**
     * Attach the leave ping to its view. GREATEST keeps it idempotent, since a
     * page can report more than once (hidden, shown again, then closed). Page
     * speed only fills in values not yet reported.
     *
     * @param  array{lcp_ms?: ?int, inp_ms?: ?int, cls?: ?float, ttfb_ms?: ?int}  $vitals
     */
    public function recordEngagement(string $viewId, int $seconds, int $scrollDepth, int $windowMinutes, array $vitals = []): bool
    {
        $set = '';
        $params = [$seconds, $scrollDepth];
        foreach (['lcp_ms', 'inp_ms', 'cls', 'ttfb_ms'] as $column) {
            if (($vitals[$column] ?? null) !== null) {
                $set .= ", {$column} = GREATEST(COALESCE({$column}, 0), ?)";
                $params[] = $vitals[$column];
            }
        }

        $sql = "UPDATE analytics_events
                SET engaged_seconds = GREATEST(COALESCE(engaged_seconds, 0), ?),
                    scroll_depth = GREATEST(COALESCE(scroll_depth, 0), ?),
                    engaged_at = UTC_TIMESTAMP(){$set}
                WHERE view_id = ? AND name = 'page_view' AND created_at > UTC_TIMESTAMP() - INTERVAL ? MINUTE";

        return $this->database->execute($sql, [...$params, $viewId, $windowMinutes]) > 0;
    }

    /**
     * A recent counted page view, for attaching an event the page sent to it.
     *
     * @return array<string, mixed>|null
     */
    public function findRecentView(string $viewId, int $windowMinutes): ?array
    {
        $row = $this->database->query(
            "SELECT e.view_id, e.visit_id, e.visitor_hash, e.visitor_kind, e.blog_id, e.post_id, e.page_type, e.path,
                    e.path_hash, e.locale, e.local_date, e.local_hour,
                    v.channel, v.referrer_host, v.referrer_source, v.utm_source, v.utm_medium, v.utm_campaign
             FROM analytics_events e
             LEFT JOIN analytics_visits v ON v.id = e.visit_id
             WHERE e.view_id = ? AND e.name = 'page_view' AND e.created_at > UTC_TIMESTAMP() - INTERVAL ? MINUTE
             LIMIT 1",
            [$viewId, $windowMinutes]
        )->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        foreach (['blog_id', 'post_id', 'local_hour'] as $column) {
            $row[$column] = $row[$column] === null ? null : (int) $row[$column];
        }

        return $row;
    }

    /**
     * Flag the page views of every visitor who, on one UTC day since $from, opened
     * at least $minViews pages without a single leave ping. People reading that much
     * close or hide a page now and then; scripts never do. Recomputed on every run,
     * so a visitor whose pings arrive later is counted again.
     *
     * @return int Views flagged
     */
    public function markSuspects(string $from, int $minViews): int
    {
        $this->database->execute(
            "UPDATE analytics_events SET suspect = 0 WHERE created_at >= ? AND suspect = 1 AND name = 'page_view'",
            [$from]
        );

        return $this->database->execute(
            "UPDATE analytics_events h
             JOIN (SELECT visitor_hash, DATE(created_at) AS day
                   FROM analytics_events
                   WHERE created_at >= ? AND name = 'page_view'
                   GROUP BY visitor_hash, DATE(created_at)
                   HAVING COUNT(*) >= ? AND SUM(engaged_seconds IS NOT NULL) = 0) s
               ON s.visitor_hash = h.visitor_hash AND DATE(h.created_at) = s.day
             SET h.suspect = 1
             WHERE h.created_at >= ? AND h.name = 'page_view'",
            [$from, $minViews, $from]
        );
    }

    /**
     * Views left out of the totals as script-like, UTC days.
     */
    public function countSuspect(string $from, string $to): int
    {
        return (int) $this->database->query(
            "SELECT COUNT(*) FROM analytics_events
             WHERE name = 'page_view' AND suspect = 1 AND created_at >= ? AND created_at < ? + INTERVAL 1 DAY",
            [$from, $to]
        )->fetchColumn();
    }

    /**
     * Blogs with an event recorded or a leave ping received since $since (UTC), the
     * ones whose totals can have changed.
     *
     * @return list<int>
     */
    public function blogsChangedSince(string $since): array
    {
        $ids = $this->database->query(
            'SELECT blog_id FROM analytics_events WHERE created_at >= ? AND blog_id IS NOT NULL
             UNION
             SELECT blog_id FROM analytics_events WHERE engaged_at >= ? AND blog_id IS NOT NULL',
            [$since, $since]
        )->fetchAll(\PDO::FETCH_COLUMN);

        return array_map('intval', $ids);
    }

    /**
     * Pages and sources of the last few minutes, busiest first. A null blog is the whole site.
     *
     * @return array{pages: list<array{value: string, views: int}>, sources: list<array{channel: string, source: ?string, views: int}>}
     */
    public function recentBreakdown(?int $blogId, int $minutes, int $limit): array
    {
        [$where, $params] = $blogId === null ? ['TRUE', []] : ['blog_id = ?', [$blogId]];
        $params[] = $minutes;
        $limit = max(1, $limit);

        $pages = $this->database->query(
            "SELECT path AS value, COUNT(*) AS views FROM analytics_events
             WHERE {$where} AND name = 'page_view' AND created_at > UTC_TIMESTAMP() - INTERVAL ? MINUTE
             GROUP BY path ORDER BY views DESC, path ASC LIMIT {$limit}",
            $params
        )->fetchAll(\PDO::FETCH_ASSOC);

        $sources = $this->database->query(
            "SELECT channel, referrer_source AS source, COUNT(*) AS views FROM analytics_events
             WHERE {$where} AND name = 'page_view' AND created_at > UTC_TIMESTAMP() - INTERVAL ? MINUTE
             GROUP BY channel, referrer_source ORDER BY views DESC, channel ASC LIMIT {$limit}",
            $params
        )->fetchAll(\PDO::FETCH_ASSOC);

        return [
            'pages' => array_map(static fn (array $r): array => ['value' => (string) $r['value'], 'views' => (int) $r['views']], $pages),
            'sources' => array_map(static fn (array $r): array => [
                'channel' => (string) $r['channel'],
                'source' => $r['source'] === null ? null : (string) $r['source'],
                'views' => (int) $r['views'],
            ], $sources),
        ];
    }

    /**
     * Discover searches over UTC days, only the terms enough different visitors
     * searched for, so nobody's one-off search is shown. With $foundNothing, only
     * the searches that found no results.
     *
     * @return list<array{value: string, searches: int, visitors: int}>
     */
    public function searchTerms(string $from, string $to, int $minVisitors, int $limit, bool $foundNothing = false): array
    {
        $empty = $foundNothing ? " AND JSON_EXTRACT(props, '$.search_results') = 0" : '';

        $rows = $this->database->query(
            "SELECT props->>'$.q' AS value, COUNT(*) AS searches, COUNT(DISTINCT visitor_hash) AS visitors
             FROM analytics_events
             WHERE name = 'page_view' AND page_type = 'discover' AND props->>'$.q' IS NOT NULL AND suspect = 0{$empty}
               AND created_at >= ? AND created_at < ? + INTERVAL 1 DAY
             GROUP BY value
             HAVING visitors >= ?
             ORDER BY searches DESC, value ASC
             LIMIT ".max(1, $limit),
            [$from, $to, $minVisitors]
        )->fetchAll(\PDO::FETCH_ASSOC);

        return array_map(static fn (array $r): array => [
            'value' => (string) $r['value'],
            'searches' => (int) $r['searches'],
            'visitors' => (int) $r['visitors'],
        ], $rows);
    }

    /**
     * Missing pages most reached first, each with the sites that sent readers there,
     * over UTC days. A null blog means the platform's own paths.
     *
     * @return list<array{path: string, views: int, referrers: list<array{host: string, views: int}>}>
     */
    public function topMissing(?int $blogId, string $from, string $to, int $limit): array
    {
        [$where, $params] = $blogId === null ? ['blog_id IS NULL', []] : ['blog_id = ?', [$blogId]];

        $rows = $this->database->query(
            "SELECT path, COALESCE(props->>'$.referrer_host', '') AS came_from_host, COUNT(*) AS views
             FROM analytics_events
             WHERE name = 'not_found' AND {$where} AND created_at >= ? AND created_at < ? + INTERVAL 1 DAY
             GROUP BY path, came_from_host",
            [...$params, $from, $to]
        )->fetchAll(\PDO::FETCH_ASSOC);

        $byPath = [];
        foreach ($rows as $row) {
            $path = (string) $row['path'];
            $byPath[$path] ??= ['path' => $path, 'views' => 0, 'referrers' => []];
            $byPath[$path]['views'] += (int) $row['views'];
            $byPath[$path]['referrers'][] = ['host' => (string) $row['came_from_host'], 'views' => (int) $row['views']];
        }

        foreach ($byPath as &$entry) {
            usort($entry['referrers'], static fn (array $a, array $b): int => [$b['views'], $a['host']] <=> [$a['views'], $b['host']]);
        }
        unset($entry);

        usort($byPath, static fn (array $a, array $b): int => [$b['views'], $a['path']] <=> [$a['views'], $b['path']]);

        return array_slice($byPath, 0, max(1, $limit));
    }

    /**
     * Split the catch-all partition into one partition per local day, through
     * $daysAhead days after today, so pruning can drop whole days. Rows already in
     * the catch-all move to their day. A table without partitions is left alone.
     *
     * @return int Partitions added
     */
    public function addDayPartitions(int $daysAhead): int
    {
        $partitions = $this->partitions();
        if (!isset($partitions['p_future'])) {
            return 0;
        }

        $utc = new \DateTimeZone('UTC');
        $last = null;
        foreach (array_keys($partitions) as $name) {
            if (preg_match('/^p(\d{8})$/', (string) $name, $m)) {
                $last = \DateTimeImmutable::createFromFormat('!Ymd', $m[1], $utc);
            }
        }

        $start = $last !== null && $last !== false
            ? $last->modify('+1 day')
            : $this->earliestFutureDay($partitions['p_start'] ?? null);
        $end = new \DateTimeImmutable('today +'.max(0, $daysAhead).' days', $utc);

        $definitions = [];
        for ($day = $start; $day <= $end && count($definitions) < 400; $day = $day->modify('+1 day')) {
            $definitions[] = sprintf(
                "PARTITION p%s VALUES LESS THAN ('%s')",
                $day->format('Ymd'),
                $day->modify('+1 day')->format('Y-m-d')
            );
        }

        if ($definitions === []) {
            return 0;
        }

        $definitions[] = 'PARTITION p_future VALUES LESS THAN (MAXVALUE)';
        $this->database->execute('ALTER TABLE analytics_events REORGANIZE PARTITION p_future INTO ('.implode(', ', $definitions).')');

        return count($definitions) - 1;
    }

    /**
     * Drop every day partition that ends on or before $bound (Y-m-d, exclusive end).
     * A local day can run up to 14 hours past its UTC date, so callers pass a bound
     * a day short of the retention cutoff and let the batched delete take the rest.
     *
     * @return int Events dropped
     */
    private function dropPartitionsBefore(string $bound): int
    {
        $old = [];
        foreach ($this->partitions() as $name => $upper) {
            if ($name !== 'p_future' && $upper <= $bound) {
                $old[] = $name;
            }
        }

        if ($old === []) {
            return 0;
        }

        $events = 0;
        foreach ($old as $name) {
            $events += (int) $this->database->query("SELECT COUNT(*) FROM analytics_events PARTITION ({$name})")->fetchColumn();
        }

        $this->database->execute('ALTER TABLE analytics_events DROP PARTITION '.implode(', ', $old));

        return $events;
    }

    /**
     * @return array<string, string> Partition name => upper bound as MySQL shows it, in order
     */
    private function partitions(): array
    {
        $rows = $this->database->query(
            "SELECT PARTITION_NAME, PARTITION_DESCRIPTION FROM information_schema.PARTITIONS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'analytics_events' AND PARTITION_NAME IS NOT NULL
             ORDER BY PARTITION_ORDINAL_POSITION"
        )->fetchAll(\PDO::FETCH_ASSOC);

        $partitions = [];
        foreach ($rows as $row) {
            $partitions[(string) $row['PARTITION_NAME']] = trim((string) $row['PARTITION_DESCRIPTION'], "'");
        }

        return $partitions;
    }

    /**
     * The first day the catch-all holds, or today when it is empty.
     */
    private function earliestFutureDay(?string $startBound): \DateTimeImmutable
    {
        $utc = new \DateTimeZone('UTC');
        $today = new \DateTimeImmutable('today', $utc);

        $earliest = $this->database->query(
            'SELECT MIN(local_date) FROM analytics_events WHERE local_date >= ?',
            [$startBound ?? '1970-01-01']
        )->fetchColumn();

        if (!$earliest) {
            return $today;
        }

        return min($today, new \DateTimeImmutable((string) $earliest, $utc));
    }

    /**
     * The page views of one visit, newest first.
     *
     * @return list<array<string, mixed>>
     */
    public function viewsOfVisit(string $visitId): array
    {
        return $this->database->query(
            "SELECT blog_id, page_type, created_at FROM analytics_events
             WHERE visit_id = ? AND name = 'page_view'
             ORDER BY created_at DESC, id DESC",
            [$visitId]
        )->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Move a visitor's recent events to the id they are known by from now on.
     *
     * @param  list<string>  $fromHashes
     * @return int Events moved
     */
    public function reassignRecent(array $fromHashes, string $toHash, string $toKind, int $minutes): int
    {
        $placeholders = implode(', ', array_fill(0, count($fromHashes), '?'));

        $sql = "UPDATE analytics_events SET visitor_hash = ?, visitor_kind = ?
                WHERE visitor_hash IN ({$placeholders}) AND created_at > UTC_TIMESTAMP() - INTERVAL ? MINUTE";

        return $this->database->execute($sql, [$toHash, $toKind, ...$fromHashes, $minutes]);
    }

    /**
     * Different visitors in the last few minutes.
     */
    public function countRecent(int $blogId, int $minutes): int
    {
        $sql = "SELECT COUNT(DISTINCT visitor_hash) FROM analytics_events
                WHERE blog_id = ? AND name = 'page_view' AND created_at > UTC_TIMESTAMP() - INTERVAL ? MINUTE";

        return (int) $this->database->query($sql, [$blogId, $minutes])->fetchColumn();
    }

    /**
     * Different visitors in the last few minutes, on any blog.
     */
    public function countRecentEverywhere(int $minutes): int
    {
        $sql = "SELECT COUNT(DISTINCT visitor_hash) FROM analytics_events
                WHERE name = 'page_view' AND created_at > UTC_TIMESTAMP() - INTERVAL ? MINUTE";

        return (int) $this->database->query($sql, [$minutes])->fetchColumn();
    }

    /**
     * Delete events older than the retention period. Whole local days go by dropping
     * their partition; the boundary days are deleted in batches so a large backlog
     * never holds one long lock on a table the beacon writes to.
     */
    public function pruneOlderThan(int $days): int
    {
        $total = $this->dropPartitionsBefore(
            (new \DateTimeImmutable('today', new \DateTimeZone('UTC')))->modify('-'.($days + 1).' days')->format('Y-m-d')
        );

        do {
            $deleted = $this->database->execute(
                'DELETE FROM analytics_events WHERE created_at < UTC_TIMESTAMP() - INTERVAL ? DAY LIMIT '.self::PRUNE_BATCH,
                [$days]
            );
            $total += $deleted;
        } while ($deleted === self::PRUNE_BATCH);

        return $total;
    }

    /**
     * Unlink a deleted blog's page views from it. They keep counting for the site,
     * so rebuilding the totals never rewrites the site's history. Its other events
     * (goals, clicks, missing pages) go with the blog: left unlinked they would be
     * counted as the platform's at the next rebuild.
     */
    public function detachBlog(int $blogId): int
    {
        $this->database->execute("DELETE FROM analytics_events WHERE blog_id = ? AND name <> 'page_view'", [$blogId]);

        return $this->database->execute('UPDATE analytics_events SET blog_id = NULL, post_id = NULL WHERE blog_id = ?', [$blogId]);
    }

    /**
     * Remove every event counted under one visitor id, used when an account is erased.
     */
    public function deleteByVisitorHash(string $visitorHash): int
    {
        return $this->database->execute('DELETE FROM analytics_events WHERE visitor_hash = ?', [$visitorHash]);
    }
}
