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
                    scroll_depth = GREATEST(COALESCE(scroll_depth, 0), ?)
                WHERE view_id = ? AND created_at > UTC_TIMESTAMP() - INTERVAL ? MINUTE';

        return $this->database->execute($sql, [$seconds, $scrollDepth, $viewId, $windowMinutes]) > 0;
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
     * Delete views older than the retention period, in batches so a large backlog
     * never holds one long lock on a table the beacon writes to.
     */
    public function pruneOlderThan(int $days): int
    {
        $total = 0;

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
