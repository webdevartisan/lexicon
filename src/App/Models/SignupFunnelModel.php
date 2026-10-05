<?php

declare(strict_types=1);

namespace App\Models;

/**
 * New accounts and what they did in their first days, for the admin Sign-ups page.
 * Counts only, worked out from the accounts' own activity.
 */
class SignupFunnelModel extends AppModel
{
    protected ?string $table = 'users';

    /**
     * Accounts created per UTC day.
     *
     * @param  string  $sharedHandle  The shared account deleted users' content moves to, which nobody signed up for
     * @return array<string, int> Y-m-d => accounts
     */
    public function accountsByDay(string $from, string $to, string $sharedHandle): array
    {
        $rows = $this->database->query(
            'SELECT DATE(created_at) AS day, COUNT(*) AS accounts
             FROM users
             WHERE created_at >= ? AND created_at < ? + INTERVAL 1 DAY AND handle <> ?
             GROUP BY DATE(created_at)',
            [$from, $to, $sharedHandle]
        )->fetchAll(\PDO::FETCH_ASSOC);

        $byDay = [];
        foreach ($rows as $row) {
            $byDay[(string) $row['day']] = (int) $row['accounts'];
        }

        return $byDay;
    }

    /**
     * What the accounts created in the range did in their first $days days. Only
     * accounts at least that old are judged, so a sign-up from yesterday doesn't
     * count as someone who did nothing.
     *
     * @return array{accounts: int, judged: int, active_readers: int, started_blog: int, published_post: int}
     */
    public function outcomes(string $from, string $to, int $days, string $sharedHandle): array
    {
        $within = static fn (string $moment): string => "{$moment} < u.created_at + INTERVAL {$days} DAY";

        $row = $this->database->query(
            "SELECT COUNT(*) AS accounts,
                    COALESCE(SUM(f.judged), 0) AS judged,
                    COALESCE(SUM(f.judged AND f.reader), 0) AS active_readers,
                    COALESCE(SUM(f.judged AND f.blog), 0) AS started_blog,
                    COALESCE(SUM(f.judged AND f.published), 0) AS published_post
             FROM (
                 SELECT u.created_at <= UTC_TIMESTAMP() - INTERVAL {$days} DAY AS judged,
                        (EXISTS (SELECT 1 FROM comments c WHERE c.user_id = u.id AND c.status <> 'spam' AND {$within('c.created_at')})
                         OR EXISTS (SELECT 1 FROM post_votes v WHERE v.user_id = u.id AND {$within('v.created_at')})
                         OR EXISTS (SELECT 1 FROM comment_votes cv WHERE cv.user_id = u.id AND {$within('cv.created_at')})
                         OR EXISTS (SELECT 1 FROM post_bookmarks pb WHERE pb.user_id = u.id AND {$within('pb.created_at')})
                         OR EXISTS (SELECT 1 FROM blog_subscribers s WHERE s.user_id = u.id AND {$within('s.created_at')})) AS reader,
                        EXISTS (SELECT 1 FROM blogs b WHERE b.owner_id = u.id AND {$within('b.created_at')}) AS blog,
                        EXISTS (SELECT 1 FROM posts p
                                WHERE p.author_id = u.id AND p.status = 'published' AND {$within('p.published_at')}) AS published
                 FROM users u
                 WHERE u.created_at >= ? AND u.created_at < ? + INTERVAL 1 DAY AND u.handle <> ?
             ) f",
            [$from, $to, $sharedHandle]
        )->fetch(\PDO::FETCH_ASSOC) ?: [];

        return [
            'accounts' => (int) ($row['accounts'] ?? 0),
            'judged' => (int) ($row['judged'] ?? 0),
            'active_readers' => (int) ($row['active_readers'] ?? 0),
            'started_blog' => (int) ($row['started_blog'] ?? 0),
            'published_post' => (int) ($row['published_post'] ?? 0),
        ];
    }
}
