<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Visits: one visitor on one device, with less than the visit gap between page
 * views. Not login sessions: a visit has no account, covers guests, and is
 * pruned with the raw events.
 */
class AnalyticsVisitModel extends AppModel
{
    protected ?string $table = 'analytics_visits';

    private const PRUNE_BATCH = 5000;

    /**
     * The visit this page view belongs to, started if the visitor has none going on
     * this device. Two first views arriving together both claim the same next
     * number, so INSERT IGNORE leaves one row and both read it back.
     *
     * @param  array{device: string, browser: string, os: string, country: ?string}  $profile
     * @param  array<string, mixed>  $entry  entry_path, entry_page_type, entry_blog_id, entry_post_id, channel,
     *                                       referrer_host, referrer_source, utm_source, utm_medium, utm_campaign
     * @return string The visit id, raw bytes
     */
    public function track(string $visitorHash, string $visitorKind, array $profile, array $entry, int $gapMinutes): string
    {
        $latest = $this->database->query(
            'SELECT id, seq, device, browser, os, country, last_seen_at > UTC_TIMESTAMP() - INTERVAL ? MINUTE AS open
             FROM analytics_visits WHERE visitor_hash = ? ORDER BY seq DESC LIMIT 1',
            [$gapMinutes, $visitorHash]
        )->fetch(\PDO::FETCH_ASSOC) ?: null;

        if ($latest !== null && (int) $latest['open'] === 1 && self::sameProfile($latest, $profile)) {
            $this->database->execute(
                'UPDATE analytics_visits SET last_seen_at = UTC_TIMESTAMP(), page_views = page_views + 1 WHERE id = ?',
                [$latest['id']]
            );

            return (string) $latest['id'];
        }

        $seq = $latest === null ? 1 : (int) $latest['seq'] + 1;
        $row = ['id' => random_bytes(16), 'visitor_hash' => $visitorHash, 'seq' => $seq, 'visitor_kind' => $visitorKind]
            + $profile + $entry;
        $columns = array_keys($row);

        $this->database->execute(
            'INSERT IGNORE INTO analytics_visits ('.implode(', ', $columns).', started_at, last_seen_at)
             VALUES ('.implode(', ', array_fill(0, count($columns), '?')).', UTC_TIMESTAMP(), UTC_TIMESTAMP())',
            array_values($row)
        );

        $id = $this->database->query(
            'SELECT id FROM analytics_visits WHERE visitor_hash = ? AND seq = ?',
            [$visitorHash, $seq]
        )->fetchColumn();

        if ($id === false) {
            throw new \RuntimeException('A visit was neither stored nor found for its visitor.');
        }

        return (string) $id;
    }

    /**
     * The newest visit still going on for any of these visitor ids, for events the
     * server records (goals, sign-ups) and attaches to the reader's visit.
     *
     * @param  list<string>  $visitorHashes
     * @return array<string, mixed>|null
     */
    public function openFor(array $visitorHashes, int $gapMinutes): ?array
    {
        $placeholders = implode(', ', array_fill(0, count($visitorHashes), '?'));

        $row = $this->database->query(
            "SELECT * FROM analytics_visits
             WHERE visitor_hash IN ({$placeholders}) AND last_seen_at > UTC_TIMESTAMP() - INTERVAL ? MINUTE
             ORDER BY last_seen_at DESC LIMIT 1",
            [...$visitorHashes, $gapMinutes]
        )->fetch(\PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Move the visits going on under the old ids to the id the reader is known by
     * now. Each moved visit takes the next free number under the new id.
     *
     * @param  list<string>  $fromHashes
     * @return list<string> The visit ids moved
     */
    public function reassignRecent(array $fromHashes, string $toHash, string $toKind, int $minutes): array
    {
        $placeholders = implode(', ', array_fill(0, count($fromHashes), '?'));
        $ids = $this->database->query(
            "SELECT id FROM analytics_visits
             WHERE visitor_hash IN ({$placeholders}) AND last_seen_at > UTC_TIMESTAMP() - INTERVAL ? MINUTE
             ORDER BY started_at",
            [...$fromHashes, $minutes]
        )->fetchAll(\PDO::FETCH_COLUMN);

        foreach ($ids as $id) {
            $this->database->execute(
                'UPDATE analytics_visits v
                 JOIN (SELECT COALESCE(MAX(seq), 0) + 1 AS next FROM analytics_visits WHERE visitor_hash = ?) n
                 SET v.visitor_hash = ?, v.visitor_kind = ?, v.seq = n.next
                 WHERE v.id = ?',
                [$toHash, $toHash, $toKind, $id]
            );
        }

        return array_map('strval', $ids);
    }

    /**
     * Delete visits that ended before the retention period, in batches.
     */
    public function pruneOlderThan(int $days): int
    {
        $total = 0;

        do {
            $deleted = $this->database->execute(
                'DELETE FROM analytics_visits WHERE last_seen_at < UTC_TIMESTAMP() - INTERVAL ? DAY LIMIT '.self::PRUNE_BATCH,
                [$days]
            );
            $total += $deleted;
        } while ($deleted === self::PRUNE_BATCH);

        return $total;
    }

    /**
     * A deleted blog stops being named as where visits began.
     */
    public function detachBlog(int $blogId): int
    {
        return $this->database->execute(
            'UPDATE analytics_visits SET entry_blog_id = NULL, entry_post_id = NULL WHERE entry_blog_id = ?',
            [$blogId]
        );
    }

    public function deleteByVisitorHash(string $visitorHash): int
    {
        return $this->database->execute('DELETE FROM analytics_visits WHERE visitor_hash = ?', [$visitorHash]);
    }

    /**
     * @param  array<string, mixed>  $visit
     * @param  array{device: string, browser: string, os: string, country: ?string}  $profile
     */
    private static function sameProfile(array $visit, array $profile): bool
    {
        return $visit['device'] === $profile['device']
            && $visit['browser'] === $profile['browser']
            && $visit['os'] === $profile['os']
            && $visit['country'] === $profile['country'];
    }
}
