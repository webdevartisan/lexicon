<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Raw page views, one row per view.
 */
class TrafficHitModel extends AppModel
{
    protected ?string $table = 'traffic_hits';

    private const PRUNE_BATCH = 5000;

    /**
     * Whether this visitor already viewed this page inside the dedupe window.
     */
    public function seenRecently(string $visitorHash, string $pathHash, int $minutes): bool
    {
        $sql = 'SELECT 1 FROM traffic_hits
                WHERE visitor_hash = ? AND path_hash = ?
                  AND created_at > UTC_TIMESTAMP() - INTERVAL ? MINUTE
                LIMIT 1';

        return (bool) $this->database->query($sql, [$visitorHash, $pathHash, $minutes])->fetchColumn();
    }

    /**
     * Store one view. A replayed view id is ignored rather than counted twice.
     *
     * @param  array<string, mixed>  $hit  Column => value, view_id and hashes as raw bytes
     * @return bool False when the view id was already stored
     */
    public function record(array $hit): bool
    {
        $columns = array_keys($hit);
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));

        $sql = 'INSERT IGNORE INTO traffic_hits ('.implode(', ', $columns).', created_at)
                VALUES ('.$placeholders.', UTC_TIMESTAMP())';

        return $this->database->execute($sql, array_values($hit)) > 0;
    }

    /**
     * Attach the leave ping to its view. GREATEST keeps it idempotent, since a
     * page can report more than once (hidden, shown again, then closed).
     */
    public function recordEngagement(string $viewId, int $seconds, int $scrollDepth, int $windowMinutes): bool
    {
        $sql = 'UPDATE traffic_hits
                SET engaged_seconds = GREATEST(COALESCE(engaged_seconds, 0), ?),
                    scroll_depth = GREATEST(COALESCE(scroll_depth, 0), ?),
                    engaged_at = UTC_TIMESTAMP()
                WHERE view_id = ? AND created_at > UTC_TIMESTAMP() - INTERVAL ? MINUTE';

        return $this->database->execute($sql, [$seconds, $scrollDepth, $viewId, $windowMinutes]) > 0;
    }

    /**
     * Where a recent view was, for attaching a link click to it.
     *
     * @return array{blog_id: ?int, post_id: ?int, local_date: string}|null
     */
    public function findRecentView(string $viewId, int $windowMinutes): ?array
    {
        $row = $this->database->query(
            'SELECT blog_id, post_id, local_date FROM traffic_hits
             WHERE view_id = ? AND created_at > UTC_TIMESTAMP() - INTERVAL ? MINUTE
             LIMIT 1',
            [$viewId, $windowMinutes]
        )->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return [
            'blog_id' => $row['blog_id'] === null ? null : (int) $row['blog_id'],
            'post_id' => $row['post_id'] === null ? null : (int) $row['post_id'],
            'local_date' => (string) $row['local_date'],
        ];
    }

    /**
     * Flag the views of every visitor who, on one UTC day since $from, opened at
     * least $minViews pages without a single leave ping. People reading that much
     * close or hide a page now and then; scripts never do. Recomputed on every run,
     * so a visitor whose pings arrive later is counted again.
     *
     * @return int Views flagged
     */
    public function markSuspects(string $from, int $minViews): int
    {
        $this->database->execute('UPDATE traffic_hits SET suspect = 0 WHERE created_at >= ? AND suspect = 1', [$from]);

        return $this->database->execute(
            'UPDATE traffic_hits h
             JOIN (SELECT visitor_hash, DATE(created_at) AS day
                   FROM traffic_hits
                   WHERE created_at >= ?
                   GROUP BY visitor_hash, DATE(created_at)
                   HAVING COUNT(*) >= ? AND SUM(engaged_seconds IS NOT NULL) = 0) s
               ON s.visitor_hash = h.visitor_hash AND DATE(h.created_at) = s.day
             SET h.suspect = 1
             WHERE h.created_at >= ?',
            [$from, $minViews, $from]
        );
    }

    /**
     * Views left out of the totals as script-like, UTC days.
     */
    public function countSuspect(string $from, string $to): int
    {
        return (int) $this->database->query(
            'SELECT COUNT(*) FROM traffic_hits WHERE suspect = 1 AND created_at >= ? AND created_at < ? + INTERVAL 1 DAY',
            [$from, $to]
        )->fetchColumn();
    }

    /**
     * Blogs with a view recorded or a leave ping received since $since (UTC), the
     * ones whose totals can have changed.
     *
     * @return list<int>
     */
    public function blogsChangedSince(string $since): array
    {
        $ids = $this->database->query(
            'SELECT blog_id FROM traffic_hits WHERE created_at >= ? AND blog_id IS NOT NULL
             UNION
             SELECT blog_id FROM traffic_hits WHERE engaged_at >= ? AND blog_id IS NOT NULL',
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
            "SELECT path AS value, COUNT(*) AS views FROM traffic_hits
             WHERE {$where} AND created_at > UTC_TIMESTAMP() - INTERVAL ? MINUTE
             GROUP BY path ORDER BY views DESC, path ASC LIMIT {$limit}",
            $params
        )->fetchAll(\PDO::FETCH_ASSOC);

        $sources = $this->database->query(
            "SELECT channel, referrer_source AS source, COUNT(*) AS views FROM traffic_hits
             WHERE {$where} AND created_at > UTC_TIMESTAMP() - INTERVAL ? MINUTE
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
     * searched for, so nobody's one-off search is shown.
     *
     * @return list<array{value: string, searches: int, visitors: int}>
     */
    public function searchTerms(string $from, string $to, int $minVisitors, int $limit): array
    {
        $rows = $this->database->query(
            'SELECT search_term AS value, COUNT(*) AS searches, COUNT(DISTINCT visitor_hash) AS visitors
             FROM traffic_hits
             WHERE search_term IS NOT NULL AND suspect = 0 AND created_at >= ? AND created_at < ? + INTERVAL 1 DAY
             GROUP BY search_term
             HAVING visitors >= ?
             ORDER BY searches DESC, value ASC
             LIMIT '.max(1, $limit),
            [$from, $to, $minVisitors]
        )->fetchAll(\PDO::FETCH_ASSOC);

        return array_map(static fn (array $r): array => [
            'value' => (string) $r['value'],
            'searches' => (int) $r['searches'],
            'visitors' => (int) $r['visitors'],
        ], $rows);
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
        $this->database->execute('ALTER TABLE traffic_hits REORGANIZE PARTITION p_future INTO ('.implode(', ', $definitions).')');

        return count($definitions) - 1;
    }

    /**
     * Drop every day partition that ends on or before $bound (Y-m-d, exclusive end).
     * A local day can run up to 14 hours past its UTC date, so callers pass a bound
     * a day short of the retention cutoff and let the batched delete take the rest.
     *
     * @return int Views dropped
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

        $views = 0;
        foreach ($old as $name) {
            $views += (int) $this->database->query("SELECT COUNT(*) FROM traffic_hits PARTITION ({$name})")->fetchColumn();
        }

        $this->database->execute('ALTER TABLE traffic_hits DROP PARTITION '.implode(', ', $old));

        return $views;
    }

    /**
     * @return array<string, string> Partition name => upper bound as MySQL shows it, in order
     */
    private function partitions(): array
    {
        $rows = $this->database->query(
            "SELECT PARTITION_NAME, PARTITION_DESCRIPTION FROM information_schema.PARTITIONS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'traffic_hits' AND PARTITION_NAME IS NOT NULL
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
            'SELECT MIN(local_date) FROM traffic_hits WHERE local_date >= ?',
            [$startBound ?? '1970-01-01']
        )->fetchColumn();

        if (!$earliest) {
            return $today;
        }

        return min($today, new \DateTimeImmutable((string) $earliest, $utc));
    }

    /**
     * A visitor's latest views, newest first, for working out where their current visit began.
     *
     * @param  list<string>  $visitorHashes
     * @return list<array<string, mixed>>
     */
    public function latestForVisitors(array $visitorHashes, int $hours, int $limit): array
    {
        $placeholders = implode(', ', array_fill(0, count($visitorHashes), '?'));

        $sql = "SELECT blog_id, page_type, channel, referrer_source, utm_source, utm_medium, utm_campaign, created_at
                FROM traffic_hits
                WHERE visitor_hash IN ({$placeholders}) AND created_at > UTC_TIMESTAMP() - INTERVAL ? HOUR
                ORDER BY created_at DESC, id DESC
                LIMIT ".max(1, $limit);

        return $this->database->query($sql, [...$visitorHashes, $hours])->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Move a visitor's recent views to the id they are known by from now on.
     *
     * @param  list<string>  $fromHashes
     * @return int Views moved
     */
    public function reassignRecent(array $fromHashes, string $toHash, string $toKind, int $minutes): int
    {
        $placeholders = implode(', ', array_fill(0, count($fromHashes), '?'));

        $sql = "UPDATE traffic_hits SET visitor_hash = ?, visitor_kind = ?
                WHERE visitor_hash IN ({$placeholders}) AND created_at > UTC_TIMESTAMP() - INTERVAL ? MINUTE";

        return $this->database->execute($sql, [$toHash, $toKind, ...$fromHashes, $minutes]);
    }

    /**
     * Different visitors in the last few minutes.
     */
    public function countRecent(int $blogId, int $minutes): int
    {
        $sql = 'SELECT COUNT(DISTINCT visitor_hash) FROM traffic_hits
                WHERE blog_id = ? AND created_at > UTC_TIMESTAMP() - INTERVAL ? MINUTE';

        return (int) $this->database->query($sql, [$blogId, $minutes])->fetchColumn();
    }

    /**
     * Different visitors in the last few minutes, on any blog.
     */
    public function countRecentEverywhere(int $minutes): int
    {
        $sql = 'SELECT COUNT(DISTINCT visitor_hash) FROM traffic_hits
                WHERE created_at > UTC_TIMESTAMP() - INTERVAL ? MINUTE';

        return (int) $this->database->query($sql, [$minutes])->fetchColumn();
    }

    /**
     * Delete views older than the retention period. Whole local days go by dropping
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
                'DELETE FROM traffic_hits WHERE created_at < UTC_TIMESTAMP() - INTERVAL ? DAY LIMIT '.self::PRUNE_BATCH,
                [$days]
            );
            $total += $deleted;
        } while ($deleted === self::PRUNE_BATCH);

        return $total;
    }

    /**
     * Unlink a deleted blog's views from it. They keep counting for the site,
     * so rebuilding the totals never rewrites the site's history.
     */
    public function detachBlog(int $blogId): int
    {
        return $this->database->execute('UPDATE traffic_hits SET blog_id = NULL, post_id = NULL WHERE blog_id = ?', [$blogId]);
    }

    /**
     * Remove every view counted under one visitor id, used when an account is erased.
     */
    public function deleteByVisitorHash(string $visitorHash): int
    {
        return $this->database->execute('DELETE FROM traffic_hits WHERE visitor_hash = ?', [$visitorHash]);
    }
}
