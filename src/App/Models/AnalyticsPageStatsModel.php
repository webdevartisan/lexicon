<?php

declare(strict_types=1);

namespace App\Models;

use App\ValueObjects\AnalyticsScope;

/**
 * Reads that only one Insights page needs: search traffic by day for SEO,
 * views by days since publishing for Content,
 * publishing for Authors, and the first day a scope has visits, which goal
 * rates per visit start from. The shared reads are in AnalyticsStatsModel.
 */
class AnalyticsPageStatsModel extends AppModel
{
    protected ?string $table = 'analytics_daily_dimensions';

    /**
     * Views and visitors per day from one channel. The caller fills the gaps.
     *
     * @return array<string, array{views: int, visitors: int}> Keyed by Y-m-d
     */
    public function channelSeries(AnalyticsScope $scope, string $channel, string $from, string $to): array
    {
        [$where, $params] = AnalyticsStatsModel::scopeWhere($scope);

        $rows = $this->database->query(
            "SELECT day, SUM(views) AS views, SUM(visitors) AS visitors
             FROM analytics_daily_dimensions
             WHERE {$where} AND dimension = 'channel' AND value = ? AND day BETWEEN ? AND ?
             GROUP BY day",
            [...$params, $channel, $from, $to]
        )->fetchAll(\PDO::FETCH_ASSOC);

        $series = [];
        foreach ($rows as $row) {
            $series[(string) $row['day']] = ['views' => (int) $row['views'], 'visitors' => (int) $row['visitors']];
        }

        return $series;
    }

    /**
     * The first day the scope counted a visit. Days before it have views but no
     * visits, so a rate per visit can only start here.
     */
    public function visitsSince(AnalyticsScope $scope): ?string
    {
        [$where, $params] = AnalyticsStatsModel::scopeWhere($scope);

        $day = $this->database->query(
            "SELECT MIN(day) FROM analytics_daily WHERE {$where} AND visits > 0",
            $params
        )->fetchColumn();

        return $day ? (string) $day : null;
    }

    /**
     * Posts each author published in the range, on one blog or every blog.
     *
     * @return array<int, int> Author id => posts published
     */
    public function postsPublished(?int $blogId, string $from, string $to): array
    {
        [$blogWhere, $params] = $blogId === null ? ['', []] : [' AND blog_id = ?', [$blogId]];

        $rows = $this->database->query(
            "SELECT author_id, COUNT(*) AS posts FROM posts
             WHERE status = 'published' AND published_at >= ? AND published_at < ? + INTERVAL 1 DAY{$blogWhere}
             GROUP BY author_id",
            [$from, $to, ...$params]
        )->fetchAll(\PDO::FETCH_KEY_PAIR);

        return array_map('intval', $rows);
    }

    /**
     * Posts published in each of the last $months calendar months up to $to, per
     * author, for how often each one publishes.
     *
     * @return array<int, array<string, int>> Author id => Y-m => posts
     */
    public function publishingByMonth(?int $blogId, string $to, int $months): array
    {
        [$blogWhere, $params] = $blogId === null ? ['', []] : [' AND blog_id = ?', [$blogId]];
        $start = (new \DateTimeImmutable($to))->modify('first day of this month')->modify('-'.($months - 1).' months');

        $rows = $this->database->query(
            "SELECT author_id, DATE_FORMAT(published_at, '%Y-%m') AS month, COUNT(*) AS posts FROM posts
             WHERE status = 'published' AND published_at >= ? AND published_at < ? + INTERVAL 1 DAY{$blogWhere}
             GROUP BY author_id, month",
            [$start->format('Y-m-d'), $to, ...$params]
        )->fetchAll(\PDO::FETCH_ASSOC);

        $byAuthor = [];
        foreach ($rows as $row) {
            $byAuthor[(int) $row['author_id']][(string) $row['month']] = (int) $row['posts'];
        }

        return $byAuthor;
    }

    /**
     * Writers whose first published post anywhere came out in the range, with
     * that post and its views in its first week.
     *
     * @return list<array{author_id: int, post_id: int, title: string, blog_id: int, blog_name: ?string, published: string, first_week: int}>
     */
    public function newWriters(string $from, string $to, int $limit): array
    {
        $rows = $this->database->query(
            "SELECT p.author_id, p.id AS post_id, p.title, p.blog_id, b.blog_name, DATE(p.published_at) AS published,
                    COALESCE((SELECT SUM(d.views) FROM analytics_daily d
                              WHERE d.scope = 'post' AND d.scope_id = p.id AND d.blog_id = p.blog_id
                                AND d.day < DATE(p.published_at) + INTERVAL 7 DAY), 0) AS first_week
             FROM posts p
             JOIN (SELECT author_id, MIN(published_at) AS first_at FROM posts
                   WHERE status = 'published' AND published_at IS NOT NULL
                   GROUP BY author_id) f ON f.author_id = p.author_id AND f.first_at = p.published_at
             LEFT JOIN blogs b ON b.id = p.blog_id
             WHERE p.status = 'published' AND p.published_at >= ? AND p.published_at < ? + INTERVAL 1 DAY
             ORDER BY p.published_at DESC
             LIMIT ".max(1, $limit),
            [$from, $to]
        )->fetchAll(\PDO::FETCH_ASSOC);

        return array_map(static fn (array $row): array => [
            'author_id' => (int) $row['author_id'],
            'post_id' => (int) $row['post_id'],
            'title' => (string) $row['title'],
            'blog_id' => (int) $row['blog_id'],
            'blog_name' => $row['blog_name'] === null ? null : (string) $row['blog_name'],
            'published' => (string) $row['published'],
            'first_week' => (int) $row['first_week'],
        ], $rows);
    }

    /**
     * Average views per post on each of the first $days days after publishing,
     * over the scope's posts published from $since to $to. A post counts on a day
     * only once it is that old, so this week's posts don't drag the later days down.
     *
     * @return array{posts: int, days: list<array{day: int, views: float, posts: int}>}
     */
    public function viewsByAge(AnalyticsScope $scope, string $since, string $to, int $days): array
    {
        $postsIn = self::postsIn($scope);
        if ($postsIn === null) {
            return ['posts' => 0, 'days' => []];
        }

        [$where, $params] = $postsIn;
        $published = "p.status = 'published' AND p.published_at >= ? AND p.published_at < ? + INTERVAL 1 DAY AND {$where}";

        $publishDays = $this->database->query(
            "SELECT DATE(p.published_at) AS day, COUNT(*) AS posts FROM posts p WHERE {$published} GROUP BY day",
            [$since, $to, ...$params]
        )->fetchAll(\PDO::FETCH_KEY_PAIR);

        if ($publishDays === []) {
            return ['posts' => 0, 'days' => []];
        }

        $viewsByAge = $this->database->query(
            "SELECT DATEDIFF(d.day, DATE(p.published_at)) AS age, SUM(d.views) AS views
             FROM posts p
             JOIN analytics_daily d ON d.scope = 'post' AND d.scope_id = p.id AND d.blog_id = p.blog_id
             WHERE {$published} AND d.day >= DATE(p.published_at) AND d.day < DATE(p.published_at) + INTERVAL ? DAY AND d.day <= ?
             GROUP BY age",
            [$since, $to, ...$params, $days, $to]
        )->fetchAll(\PDO::FETCH_KEY_PAIR);

        $end = new \DateTimeImmutable($to);
        $curve = [];
        for ($age = 0; $age < $days; $age++) {
            $lastPublishDay = $end->modify("-{$age} days")->format('Y-m-d');
            $old = array_sum(array_filter($publishDays, static fn (string $day): bool => $day <= $lastPublishDay, ARRAY_FILTER_USE_KEY));
            if ($old === 0) {
                break;
            }

            $curve[] = ['day' => $age, 'views' => (int) ($viewsByAge[$age] ?? 0) / $old, 'posts' => (int) $old];
        }

        return ['posts' => (int) array_sum($publishDays), 'days' => $curve];
    }

    /**
     * The scope's posts as a condition on the alias p, or null for a scope without posts.
     *
     * @return array{0: string, 1: list<int>}|null
     */
    private static function postsIn(AnalyticsScope $scope): ?array
    {
        return match ($scope->type) {
            AnalyticsScope::SITE => ['1 = 1', []],
            AnalyticsScope::BLOG => ['p.blog_id = ?', [(int) $scope->blogId]],
            AnalyticsScope::POST => $scope->ids === []
                ? ['1 = 0', []]
                : ['p.id IN ('.implode(', ', array_fill(0, count($scope->ids), '?')).')', array_map('intval', $scope->ids)],
            default => null,
        };
    }
}
