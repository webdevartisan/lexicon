<?php

declare(strict_types=1);

namespace App\Database\Seeders;

use App\Models\TrafficRollupModel;
use Framework\Database;

/**
 * Invents a month of page views for a few blogs, for looking at the Traffic
 * dashboard locally. The rollups are rebuilt the way the scheduled job does it.
 */
final class TrafficSeeder
{
    private const BATCH = 500;

    private const TABLES = [
        'traffic_hits',
        'traffic_daily',
        'traffic_daily_dimensions',
        'traffic_site_daily',
        'traffic_salts',
    ];

    /** Weighted picks: value => weight. */
    private const CHANNELS = [
        'search|Google|google.com' => 34, 'search|DuckDuckGo|duckduckgo.com' => 4, 'search|Bing|bing.com' => 3,
        'social|X|x.com' => 7, 'social|Facebook|facebook.com' => 6, 'social|Reddit|reddit.com' => 4,
        'social|Hacker News|news.ycombinator.com' => 2, 'social|LinkedIn|linkedin.com' => 2,
        'email|Gmail|mail.google.com' => 3, 'referral|medium.com|medium.com' => 2,
        'referral|dev.to|dev.to' => 1, 'direct||' => 32,
    ];

    private const DEVICES = [
        'desktop|Chrome|Windows' => 22, 'desktop|Firefox|Windows' => 5, 'desktop|Edge|Windows' => 5,
        'desktop|Safari|macOS' => 9, 'desktop|Chrome|macOS' => 7, 'desktop|Firefox|Linux' => 3,
        'mobile|Safari|iOS' => 21, 'mobile|Chrome|Android' => 20, 'mobile|Samsung Internet|Android' => 3,
        'tablet|Safari|iOS' => 4, 'tablet|Chrome|Android' => 1,
    ];

    private const COUNTRIES = [
        'GR' => 26, 'US' => 18, 'GB' => 8, 'DE' => 7, 'CY' => 5, 'FR' => 4, 'NL' => 3, 'IT' => 3,
        'CA' => 3, 'EG' => 3, 'SA' => 2, 'AE' => 2, 'AU' => 2, 'IN' => 2, 'MD' => 1, 'IS' => 1,
    ];

    private const LOCALES = ['en' => 55, 'el' => 35, 'ar' => 10];

    private const CAMPAIGNS = [
        ['newsletter', 'email', 'weekly-digest'],
        ['x', 'social', 'launch-thread'],
        ['facebook', 'paid', 'spring-promo'],
    ];

    public function __construct(
        private Database $db,
        private TrafficRollupModel $rollups,
    ) {}

    /**
     * @param  list<int>  $blogIds  Empty for the three blogs with the most published posts
     * @return array<int, int> Views written per blog id
     */
    public function run(int $days, array $blogIds): array
    {
        $blogs = $this->blogs($blogIds);

        if ($blogs === []) {
            throw new \RuntimeException('No published blog with published posts to seed. Run php cli db:seed first.');
        }

        $written = [];
        $scale = [1 => 120, 2 => 45, 3 => 15];
        $rank = 1;

        foreach ($blogs as $blog) {
            $written[(int) $blog['id']] = $this->seedBlog($blog, $days, $scale[$rank] ?? 10);
            $rank++;
        }

        $from = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->modify('-'.($days + 1).' days');
        $this->rollups->rebuildFrom($from->format('Y-m-d'), 30, 10);

        return $written;
    }

    /**
     * Remove every page view and rollup.
     */
    public function reset(): void
    {
        foreach (self::TABLES as $table) {
            $this->db->execute("DELETE FROM {$table}");
        }
    }

    /**
     * @param  list<int>  $blogIds
     * @return list<array<string, mixed>>
     */
    private function blogs(array $blogIds): array
    {
        $where = $blogIds === [] ? '' : 'AND b.id IN ('.implode(',', array_map('intval', $blogIds)).')';

        return $this->db->query(
            "SELECT b.id, b.blog_slug, COALESCE(s.timezone, 'UTC') AS timezone, COUNT(p.id) AS posts
             FROM blogs b
             JOIN posts p ON p.blog_id = b.id AND p.status = 'published'
             LEFT JOIN blog_settings s ON s.blog_id = b.id
             WHERE b.status = 'published' {$where}
             GROUP BY b.id, b.blog_slug, s.timezone
             ORDER BY posts DESC, b.id
             LIMIT ".($blogIds === [] ? 3 : count($blogIds))
        )->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * @param  array<string, mixed>  $blog
     */
    private function seedBlog(array $blog, int $days, int $dailyVisitors): int
    {
        $pages = $this->pages($blog);
        $knownZone = in_array($blog['timezone'], \DateTimeZone::listIdentifiers(), true);
        $zone = new \DateTimeZone($knownZone ? $blog['timezone'] : 'UTC');
        $regulars = array_map(static fn (): string => random_bytes(16), range(1, max(5, intdiv($dailyVisitors, 3))));
        $rows = [];
        $total = 0;

        for ($ago = $days - 1; $ago >= 0; $ago--) {
            $day = new \DateTimeImmutable("today -{$ago} days", $zone);
            // Weekends are quieter, and a blog grows a little over the month.
            $weekday = (int) $day->format('N') >= 6 ? 0.7 : 1.0;
            $growth = 0.8 + 0.4 * (1 - $ago / max(1, $days));
            $count = (int) round($dailyVisitors * $weekday * $growth * (0.85 + mt_rand(0, 30) / 100));

            for ($v = 0; $v < $count; $v++) {
                foreach ($this->visit($blog, $pages, $day, $zone, $regulars) as $row) {
                    $rows[] = $row;
                }

                if (count($rows) >= self::BATCH) {
                    $total += $this->insert($rows);
                    $rows = [];
                }
            }
        }

        return $total + $this->insert($rows);
    }

    /**
     * One visitor's views on one day.
     *
     * @param  array<string, mixed>  $blog
     * @param  list<array{0: string, 1: ?int, 2: string, 3: int}>  $pages  type, post id, path, weight
     * @param  list<string>  $regulars
     * @return list<list<mixed>>
     */
    private function visit(
        array $blog,
        array $pages,
        \DateTimeImmutable $day,
        \DateTimeZone $zone,
        array $regulars
    ): array {
        [$hash, $kind] = $this->visitor($regulars);
        $arrival = $this->arrival();
        $profile = $this->profile();
        $views = mt_rand(1, 100) <= 55 ? 1 : mt_rand(2, 5);
        $moment = $day->setTime(mt_rand(6, 23), mt_rand(0, 59), mt_rand(0, 59));
        $rows = [];

        for ($i = 0; $i < $views; $i++) {
            [$type, $postId, $path] = $this->pickPage($pages);
            $seconds = mt_rand(1, 100) <= 85 ? $this->readingTime($type) : null;
            $local = $moment->modify('+'.($i * mt_rand(40, 240)).' seconds');

            if ($local > new \DateTimeImmutable('now', $zone)) {
                break;
            }

            $rows[] = [
                random_bytes(16), (int) $blog['id'], $postId, $type, $path, substr(hash('sha256', $path, true), 0, 8),
                $hash, $kind,
                ...($i === 0 ? $arrival : ['internal', null, null]),
                ...$profile,
                $seconds, $seconds === null ? null : min(100, 20 + (int) round($seconds / 3) + mt_rand(0, 20)),
                $local->format('Y-m-d'),
                $local->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
            ];
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $blog
     * @return list<array{0: string, 1: ?int, 2: string, 3: int}>
     */
    private function pages(array $blog): array
    {
        $base = '/blog/'.$blog['blog_slug'];
        $pages = [['landing', null, $base, 30], ['archive', null, $base.'/archive', 5]];

        $posts = $this->db->query(
            "SELECT id, slug FROM posts WHERE blog_id = ? AND status = 'published' ORDER BY published_at DESC LIMIT 40",
            [(int) $blog['id']]
        )->fetchAll(\PDO::FETCH_ASSOC);

        // A few posts do most of the work, the way real blogs look.
        foreach ($posts as $rank => $post) {
            $pages[] = ['post', (int) $post['id'], $base.'/'.$post['slug'], max(1, (int) round(40 / ($rank + 1)))];
        }

        foreach (['category' => ['categories', 2], 'tag' => ['tags', 1]] as $type => [$table, $weight]) {
            $slugs = $this->db->query("SELECT slug FROM {$table} WHERE blog_id = ? LIMIT 5", [(int) $blog['id']]);
            foreach ($slugs->fetchAll(\PDO::FETCH_COLUMN) as $slug) {
                $pages[] = [$type, null, $base.'/'.$type.'/'.$slug, $weight];
            }
        }

        return $pages;
    }

    /**
     * @param  list<array{0: string, 1: ?int, 2: string, 3: int}>  $pages
     * @return array{0: string, 1: ?int, 2: string}
     */
    private function pickPage(array $pages): array
    {
        $weights = [];
        foreach ($pages as $i => $page) {
            $weights[$i] = $page[3];
        }

        $page = $pages[(int) $this->pick($weights)];

        return [$page[0], $page[1], $page[2]];
    }

    private function readingTime(string $type): int
    {
        if ($type !== 'post') {
            return mt_rand(3, 60);
        }

        return mt_rand(1, 100) <= 25 ? mt_rand(2, 12) : mt_rand(20, 420);
    }

    /**
     * @return array{0: string, 1: ?string, 2: ?string} Channel, referrer host and source
     */
    private function arrival(): array
    {
        [$channel, $source, $host] = explode('|', $this->pick(self::CHANNELS));

        return [$channel, $host !== '' ? $host : null, $source !== '' ? $source : null];
    }

    /**
     * @return list<?string> Campaign source, medium and name, device, browser, os, country, locale
     */
    private function profile(): array
    {
        $campaign = mt_rand(1, 12) === 1 ? self::CAMPAIGNS[array_rand(self::CAMPAIGNS)] : [null, null, null];
        $country = mt_rand(1, 20) === 1 ? null : $this->pick(self::COUNTRIES);

        return [...$campaign, ...explode('|', $this->pick(self::DEVICES)), $country, $this->pick(self::LOCALES)];
    }

    /**
     * @param  list<string>  $regulars
     * @return array{0: string, 1: string} Hash and kind
     */
    private function visitor(array $regulars): array
    {
        $roll = mt_rand(1, 100);

        if ($roll <= 8) {
            return [$regulars[array_rand($regulars)], 'account'];
        }

        if ($roll <= 30) {
            return [mt_rand(1, 3) === 1 ? $regulars[array_rand($regulars)] : random_bytes(16), 'cookie'];
        }

        return [random_bytes(16), 'daily'];
    }

    /**
     * @param  array<string|int, int>  $weights
     */
    private function pick(array $weights): string
    {
        $roll = mt_rand(1, array_sum($weights));

        foreach ($weights as $value => $weight) {
            $roll -= $weight;
            if ($roll <= 0) {
                return (string) $value;
            }
        }

        return (string) array_key_last($weights);
    }

    /**
     * @param  list<list<mixed>>  $rows
     */
    private function insert(array $rows): int
    {
        if ($rows === []) {
            return 0;
        }

        $columns = 'view_id, blog_id, post_id, page_type, path, path_hash, visitor_hash, visitor_kind, channel,
                    referrer_host, referrer_source, utm_source, utm_medium, utm_campaign, device, browser, os,
                    country, locale, engaged_seconds, scroll_depth, local_date, created_at';
        $placeholders = '('.implode(', ', array_fill(0, 23, '?')).')';

        return $this->db->execute(
            "INSERT INTO traffic_hits ({$columns}) VALUES ".implode(', ', array_fill(0, count($rows), $placeholders)),
            array_merge(...$rows)
        );
    }
}
