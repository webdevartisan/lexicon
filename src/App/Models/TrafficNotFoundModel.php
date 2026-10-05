<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Readers who landed on a page that doesn't exist, per path and referring site.
 */
class TrafficNotFoundModel extends AppModel
{
    protected ?string $table = 'traffic_not_found';

    /**
     * @param  string  $referrerHost  Empty when the address was typed or the referrer hidden
     */
    public function record(string $path, string $referrerHost, ?int $blogId): void
    {
        $this->database->execute(
            'INSERT INTO traffic_not_found (day, path_hash, referrer_host, blog_id, path, views)
             VALUES (UTC_DATE(), ?, ?, ?, ?, 1)
             ON DUPLICATE KEY UPDATE views = views + 1',
            [substr(hash('sha256', $path, true), 0, 8), $referrerHost, $blogId, $path]
        );
    }

    /**
     * Missing pages most reached first, each with the sites that sent readers there.
     * A null blog means the platform's own paths.
     *
     * @return list<array{path: string, views: int, referrers: list<array{host: string, views: int}>}>
     */
    public function topMissing(?int $blogId, string $from, string $to, int $limit): array
    {
        [$where, $params] = $blogId === null ? ['blog_id IS NULL', []] : ['blog_id = ?', [$blogId]];

        $rows = $this->database->query(
            "SELECT path, referrer_host, SUM(views) AS views FROM traffic_not_found
             WHERE {$where} AND day BETWEEN ? AND ?
             GROUP BY path, referrer_host",
            [...$params, $from, $to]
        )->fetchAll(\PDO::FETCH_ASSOC);

        $byPath = [];
        foreach ($rows as $row) {
            $path = (string) $row['path'];
            $byPath[$path] ??= ['path' => $path, 'views' => 0, 'referrers' => []];
            $byPath[$path]['views'] += (int) $row['views'];
            $byPath[$path]['referrers'][] = ['host' => (string) $row['referrer_host'], 'views' => (int) $row['views']];
        }

        foreach ($byPath as &$entry) {
            usort($entry['referrers'], static fn (array $a, array $b): int => $b['views'] <=> $a['views']);
        }
        unset($entry);

        usort($byPath, static fn (array $a, array $b): int => [$b['views'], $a['path']] <=> [$a['views'], $b['path']]);

        return array_slice($byPath, 0, max(1, $limit));
    }

    /**
     * Missing-page rows older than raw retention: the paths can carry what a
     * reader typed, so they don't outlive the views they came with.
     */
    public function pruneOlderThan(int $days): int
    {
        return $this->database->execute('DELETE FROM traffic_not_found WHERE day < UTC_DATE() - INTERVAL ? DAY', [$days]);
    }
}
