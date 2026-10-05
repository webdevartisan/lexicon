<?php

declare(strict_types=1);

namespace App\Models;

/**
 * What traffic notices have gone out: view milestones per post and spike
 * notices per blog and day, so each is sent once.
 */
class TrafficNoticeModel extends AppModel
{
    protected ?string $table = 'traffic_milestones';

    /**
     * Posts with at least $min views in all, with their blog.
     *
     * @return list<array{post_id: int, blog_id: int, views: int}>
     */
    public function postsWithViews(int $min): array
    {
        $rows = $this->database->query(
            "SELECT scope_id AS post_id, blog_id, SUM(views) AS views
             FROM traffic_daily
             WHERE scope = 'post'
             GROUP BY scope_id, blog_id
             HAVING views >= ?",
            [$min]
        )->fetchAll(\PDO::FETCH_ASSOC);

        return array_map(static fn (array $row): array => [
            'post_id' => (int) $row['post_id'],
            'blog_id' => (int) $row['blog_id'],
            'views' => (int) $row['views'],
        ], $rows);
    }

    /**
     * @return array<int, list<int>> Post id => thresholds already announced
     */
    public function reachedMilestones(): array
    {
        $reached = [];
        foreach ($this->database->query('SELECT post_id, threshold FROM traffic_milestones')->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $reached[(int) $row['post_id']][] = (int) $row['threshold'];
        }

        return $reached;
    }

    public function hasAnyMilestone(): bool
    {
        return (bool) $this->database->query('SELECT 1 FROM traffic_milestones LIMIT 1')->fetchColumn();
    }

    public function recordMilestone(int $postId, int $threshold): void
    {
        $this->database->execute(
            'INSERT IGNORE INTO traffic_milestones (post_id, threshold, reached_at) VALUES (?, ?, UTC_TIMESTAMP())',
            [$postId, $threshold]
        );
    }

    /**
     * Blogs that had at least $min views on a day from $day on, the only ones a
     * spike is possible for.
     *
     * @return list<int>
     */
    public function busyBlogsSince(string $day, int $min): array
    {
        return array_map('intval', $this->database->query(
            "SELECT DISTINCT scope_id FROM traffic_daily WHERE scope = 'blog' AND day >= ? AND views >= ?",
            [$day, $min]
        )->fetchAll(\PDO::FETCH_COLUMN));
    }

    /**
     * Mark a blog's spike day as announced. False when it already was, so two
     * runs can't both send it.
     */
    public function claimSpike(int $blogId, string $day): bool
    {
        return $this->database->execute(
            'INSERT IGNORE INTO traffic_spike_notices (blog_id, day) VALUES (?, ?)',
            [$blogId, $day]
        ) > 0;
    }
}
