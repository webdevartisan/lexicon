<?php

declare(strict_types=1);

namespace App\Database\Seeders;

use App\Models\AnalyticsRollupModel;
use App\Services\Analytics\LexiconSource;
use Framework\Database;

/**
 * Invents a month of visits to a few blogs and the platform's own pages, with
 * the goals, link clicks, missing pages and beacon outcomes that go with them,
 * plus a year of older goals, clicks and sign-ups, for looking at the Insights
 * pages locally. The rollups are rebuilt from the oldest seeded day.
 */
final class AnalyticsSeeder
{
    private const BATCH = 500;

    /** How far back the goals, clicks and sign-ups go, past a 12-month range. */
    private const HISTORY_DAYS = 395;

    private const TABLES = [
        'analytics_events',
        'analytics_visits',
        'analytics_daily',
        'analytics_daily_dimensions',
        'analytics_daily_events',
        'analytics_salts',
        'analytics_beacon_outcomes',
        'analytics_notices_sent',
    ];

    private const EVENT_COLUMNS = [
        'event_key', 'name', 'view_id', 'visit_id', 'visitor_hash', 'visitor_kind', 'blog_id', 'post_id',
        'from_post_id', 'page_type', 'path', 'path_hash', 'locale', 'channel', 'referrer_host', 'referrer_source',
        'utm_source', 'utm_medium', 'utm_campaign', 'engaged_seconds', 'scroll_depth', 'engaged_at', 'props',
        'lcp_ms', 'inp_ms', 'cls', 'ttfb_ms', 'local_date', 'local_hour', 'created_at',
    ];

    private const VISIT_COLUMNS = [
        'id', 'visitor_hash', 'seq', 'visitor_kind', 'started_at', 'last_seen_at', 'page_views', 'entry_path',
        'entry_page_type', 'entry_blog_id', 'entry_post_id', 'channel', 'referrer_host', 'referrer_source',
        'utm_source', 'utm_medium', 'utm_campaign', 'device', 'browser', 'os', 'country',
    ];

    /** Chance per view of a post, in percent. */
    private const GOALS = ['like' => 4, 'save' => 2, 'comment' => 2, 'dislike' => 1];

    /** Goals a reader reaches once a visit, however often they press the button. */
    private const ONCE_PER_VISIT = ['like', 'save', 'dislike'];

    private const SHARES = ['copy' => 4, 'x' => 3, 'facebook' => 2, 'linkedin' => 1];

    /** Chance per visit to a blog, in percent. */
    private const SUBSCRIBE_PERCENT = 1;

    private const SEARCHES = [
        'photography' => 6, 'travel' => 5, 'recipes' => 4, 'web design' => 4, 'gardening' => 3,
        'history' => 3, 'poetry' => 2, 'running' => 2, 'climate' => 1,
    ];

    /** Searches Discover has nothing for, picked often enough to clear the Content page's visitor floor. */
    private const EMPTY_SEARCHES = ['knitting patterns' => 3, 'vegan baking' => 2, 'drone laws' => 1];

    private const SERVER_ERRORS = [500 => 6, 502 => 2, 503 => 2];

    private const OUTBOUND = [
        'github.com' => 6, 'wikipedia.org' => 5, 'youtube.com' => 4, 'developer.mozilla.org' => 3,
        'nytimes.com' => 2, 'arxiv.org' => 1,
    ];

    private const DOWNLOADS = ['reading-list.pdf' => 4, 'field-notes.epub' => 2, 'dataset.csv' => 1];

    /** Paths that used to exist, and who still links to them. */
    private const MISSING = [
        ['/old-post', 'google.com'], ['/2019/hello-world', 'news.ycombinator.com'], ['/feed.xml', ''],
        ['/about-me', 'reddit.com'], ['/category/misc', ''],
    ];

    /** Weighted picks: value => weight. */
    private const CHANNELS = [
        'search|Google|google.com' => 34, 'search|DuckDuckGo|duckduckgo.com' => 4, 'search|Bing|bing.com' => 3,
        'social|X|x.com' => 7, 'social|Facebook|facebook.com' => 6, 'social|Reddit|reddit.com' => 4,
        'social|Hacker News|news.ycombinator.com' => 2, 'social|LinkedIn|linkedin.com' => 2,
        'email|Gmail|mail.google.com' => 3, 'referral|medium.com|medium.com' => 2,
        'referral|dev.to|dev.to' => 1, 'direct||' => 32,
    ];

    /** Where on Lexicon a reader came from, when it was another part of it. */
    private const LEXICON_ARRIVALS = ['discover' => 5, 'home' => 4, 'blog' => 3, 'profile' => 2, 'guide' => 1, 'other' => 1];

    /** The last page read before signing up. */
    private const SIGNUP_PAGES = ['blog' => 10, 'home' => 4, 'discover' => 3, 'auth' => 2, 'guide' => 1];

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

    /** The platform's own pages: type, post id, path, weight. */
    private const PLATFORM_PAGES = [
        ['home', null, '/', 40],
        ['discover', null, '/discover', 20],
        ['guide', null, '/getting-started', 6],
        ['guide', null, '/getting-started/start-your-first-blog', 4],
        ['guide', null, '/getting-started/write-posts-people-read', 3],
        ['guide', null, '/getting-started/blog-with-your-team', 2],
        ['static_page', null, '/about', 5],
        ['static_page', null, '/privacy', 2],
        ['static_page', null, '/terms', 1],
        ['static_page', null, '/contact', 2],
        ['profile', null, '/profile', 6],
        ['auth', null, '/login', 8],
        ['auth', null, '/register', 5],
    ];

    private const CAMPAIGNS = [
        ['newsletter', 'email', 'weekly-digest'],
        ['x', 'social', 'launch-thread'],
        ['facebook', 'paid', 'spring-promo'],
    ];

    /** @var list<int> The blogs being seeded, which send readers to each other. */
    private array $blogIds = [];

    /** @var array<string, int> Visitor hash => visits so far, for each visit's number */
    private array $visitCounts = [];

    /** @var list<array<string, mixed>> */
    private array $visits = [];

    /** @var list<array<string, mixed>> */
    private array $events = [];

    public function __construct(
        private Database $db,
        private AnalyticsRollupModel $rollups,
    ) {}

    /**
     * @param  list<int>  $blogIds  Empty for the three blogs with the most published posts and the platform's pages
     * @return array<string, string> What was written, keyed "Blog 5", "Platform pages" or "Sign-ups"
     */
    public function run(int $days, array $blogIds): array
    {
        $blogs = $this->blogs($blogIds);

        if ($blogs === []) {
            throw new \RuntimeException('No published blog with published posts to seed. Run php cli db:seed first.');
        }

        $this->blogIds = array_map(static fn (array $blog): int => (int) $blog['id'], $blogs);
        $written = [];
        $scale = [1 => 120, 2 => 45, 3 => 15];
        $rank = 1;

        foreach ($blogs as $blog) {
            $written['Blog '.(int) $blog['id']] = $this->seedPages(
                (int) $blog['id'],
                new \DateTimeZone(self::zone($blog)),
                $this->pages($blog),
                $days,
                $scale[$rank] ?? 10
            ).' visits';
            $rank++;
        }

        $oldest = $days + 1;
        if ($blogIds === []) {
            $written['Platform pages'] = $this->seedPages(null, new \DateTimeZone('UTC'), self::PLATFORM_PAGES, $days, 60).' visits';
            $written['Sign-ups'] = $this->seedSignups(0, $days).' sign-up events, one per account created';
            $written['Older history'] = $this->seedHistory($blogs, $days).' goals, clicks and sign-ups from before the raw window';
            $oldest = self::HISTORY_DAYS;
        }

        $written['Missing pages'] = $this->seedMissing($blogs, $days).' events';
        $written['Server errors'] = $this->seedServerErrors($blogs, $days).' events';
        $this->flush();
        $written['Beacon outcomes'] = $this->seedOutcomes($days).' days';

        $from = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->modify("-{$oldest} days");
        $this->rollups->rebuildFrom($from->format('Y-m-d'), 30, 10);

        return $written;
    }

    /**
     * Remove every event, visit and rollup.
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
    private static function zone(array $blog): string
    {
        return in_array($blog['timezone'], \DateTimeZone::listIdentifiers(), true) ? (string) $blog['timezone'] : 'UTC';
    }

    /**
     * A month of visits to one blog, or to the platform's own pages when $blogId is null.
     *
     * @param  list<array{0: string, 1: ?int, 2: string, 3: int}>  $pages  type, post id, path, weight
     * @return int Visits written
     */
    private function seedPages(?int $blogId, \DateTimeZone $zone, array $pages, int $days, int $dailyVisitors): int
    {
        $regulars = array_map(static fn (): string => random_bytes(16), range(1, max(5, intdiv($dailyVisitors, 3))));
        $total = 0;

        for ($ago = $days - 1; $ago >= 0; $ago--) {
            $day = new \DateTimeImmutable("today -{$ago} days", $zone);
            // Weekends are quieter, and a blog grows a little over the month.
            $weekday = (int) $day->format('N') >= 6 ? 0.7 : 1.0;
            $growth = 0.8 + 0.4 * (1 - $ago / max(1, $days));
            $count = (int) round($dailyVisitors * $weekday * $growth * (0.85 + mt_rand(0, 30) / 100));

            for ($v = 0; $v < $count; $v++) {
                $total += $this->visit($blogId, $pages, $day, $zone, $regulars) ? 1 : 0;
            }
        }

        return $total;
    }

    /**
     * A few broken links per blog, spread over the period.
     *
     * @param  list<array<string, mixed>>  $blogs
     */
    private function seedMissing(array $blogs, int $days): int
    {
        $written = 0;

        foreach ($blogs as $blog) {
            foreach (self::MISSING as [$tail, $host]) {
                $path = '/blog/'.$blog['blog_slug'].$tail;

                for ($ago = 0; $ago < $days; $ago += mt_rand(2, 6)) {
                    $moment = new \DateTimeImmutable("today -{$ago} days 12:00", new \DateTimeZone('UTC'));

                    for ($i = mt_rand(1, 4); $i > 0; $i--) {
                        $this->queueEvent([
                            'name' => 'not_found',
                            'blog_id' => (int) $blog['id'],
                            'path' => $path,
                            'path_hash' => substr(hash('sha256', $path, true), 0, 8),
                            'props' => $host !== '' ? ['referrer_host' => $host] : null,
                        ], $moment);
                        $written++;
                    }
                }
            }
        }

        return $written;
    }

    /**
     * A few failed requests a day on pages that exist, the only thing a server error keeps.
     *
     * @param  list<array<string, mixed>>  $blogs
     */
    private function seedServerErrors(array $blogs, int $days): int
    {
        $paths = ['/discover'];
        foreach ($blogs as $blog) {
            $paths = [...$paths, ...array_column(array_slice($this->pages($blog), 0, 10), 2)];
        }

        $written = 0;
        for ($ago = 0; $ago < $days; $ago++) {
            for ($i = mt_rand(0, 3); $i > 0; $i--) {
                $path = $paths[array_rand($paths)];
                $this->queueEvent([
                    'name' => 'server_error',
                    'path' => $path,
                    'path_hash' => substr(hash('sha256', $path, true), 0, 8),
                    'props' => ['status' => (int) $this->pick(self::SERVER_ERRORS)],
                ], new \DateTimeImmutable("today -{$ago} days ".$this->hour().':'.mt_rand(10, 59), new \DateTimeZone('UTC')));
                $written++;
            }
        }

        return $written;
    }

    /**
     * What happened to each day's beacons: the views stored, plus believable
     * shares turned away by each check.
     */
    private function seedOutcomes(int $days): int
    {
        $recorded = $this->db->query(
            "SELECT DATE(created_at) AS day, COUNT(*) AS views FROM analytics_events
             WHERE name = 'page_view' AND created_at >= UTC_DATE() - INTERVAL ? DAY GROUP BY DATE(created_at)",
            [$days]
        )->fetchAll(\PDO::FETCH_KEY_PAIR);
        $shares = ['bot' => 0.18, 'duplicate' => 0.09, 'opted_out' => 0.04, 'hosting' => 0.03, 'unknown_page' => 0.01, 'member' => 0.02];
        $upsert = 'INSERT INTO analytics_beacon_outcomes (day, outcome, requests) VALUES (?, ?, ?)
                   ON DUPLICATE KEY UPDATE requests = VALUES(requests)';

        foreach ($recorded as $day => $views) {
            $this->db->execute($upsert, [$day, 'recorded', (int) $views]);

            foreach ($shares as $outcome => $share) {
                $this->db->execute($upsert, [$day, $outcome, (int) round($views * $share * (0.7 + mt_rand(0, 60) / 100))]);
            }
        }

        return count($recorded);
    }

    /**
     * A year of goals, clicks and sign-ups older than the seeded views. Those
     * outlive the raw events in the daily counts, so a 12-month range has to show them.
     *
     * @param  list<array<string, mixed>>  $blogs
     */
    private function seedHistory(array $blogs, int $days): int
    {
        $written = 0;

        foreach ($blogs as $rank => $blog) {
            $zone = new \DateTimeZone(self::zone($blog));
            $posts = array_values(array_filter(array_column($this->pages($blog), 1)));

            for ($ago = $days + 1; $ago < self::HISTORY_DAYS; $ago++) {
                $moment = new \DateTimeImmutable("today -{$ago} days 12:00", $zone);

                for ($visit = mt_rand(0, intdiv(12, $rank + 1)); $visit > 0; $visit--) {
                    $written += $this->actOn(null, (int) $blog['id'], $posts === [] ? null : $posts[array_rand($posts)], $moment, true);
                }
            }
        }

        return $written + $this->seedSignups($days + 1, self::HISTORY_DAYS);
    }

    /**
     * One sign-up event for each account created between $fromDays and $toDays
     * ago, so the Sign-ups page lists sources for the accounts it counts.
     */
    private function seedSignups(int $fromDays, int $toDays): int
    {
        $accountDays = $this->db->query(
            'SELECT DATE(created_at) FROM users
             WHERE created_at >= UTC_DATE() - INTERVAL ? DAY AND created_at < UTC_DATE() - INTERVAL ? DAY + INTERVAL 1 DAY',
            [$toDays, $fromDays]
        )->fetchAll(\PDO::FETCH_COLUMN);

        foreach ($accountDays as $day) {
            [$channel, $source] = explode('|', $this->pick(self::CHANNELS));
            $page = $this->pick(self::SIGNUP_PAGES);
            $cameFrom = $page === 'blog' ? LexiconSource::blog($this->blogIds[array_rand($this->blogIds)]) : $page;

            $this->queueEvent([
                'name' => 'signup',
                'channel' => $channel,
                'referrer_source' => $source !== '' ? $source : null,
                'props' => ['came_from' => $cameFrom],
            ], new \DateTimeImmutable("{$day} 12:00", new \DateTimeZone('UTC')));
        }

        return count($accountDays);
    }

    /**
     * One visitor's visit on one day: the visit and its page views, and what they did.
     *
     * @param  list<array{0: string, 1: ?int, 2: string, 3: int}>  $pages  type, post id, path, weight
     * @param  list<string>  $regulars
     * @return bool False when the visit would have started in the future
     */
    private function visit(?int $blogId, array $pages, \DateTimeImmutable $day, \DateTimeZone $zone, array $regulars): bool
    {
        [$hash, $kind] = $this->visitor($regulars);
        [$channel, $referrerHost, $referrerSource] = $this->arrival($blogId);
        [$utmSource, $utmMedium, $utmCampaign, $device, $browser, $os, $country, $locale] = $this->profile();
        $views = mt_rand(1, 100) <= 55 ? 1 : mt_rand(2, 5);
        $moment = $day->setTime($this->hour(), mt_rand(0, 59), mt_rand(0, 59));
        $now = new \DateTimeImmutable('now', $zone);

        if ($moment > $now) {
            return false;
        }

        $visit = [
            'id' => random_bytes(16),
            'visitor_hash' => $hash,
            'seq' => $this->visitCounts[$hash] = ($this->visitCounts[$hash] ?? 0) + 1,
            'visitor_kind' => $kind,
            'channel' => $channel,
            'referrer_host' => $referrerHost,
            'referrer_source' => $referrerSource,
            'utm_source' => $utmSource,
            'utm_medium' => $utmMedium,
            'utm_campaign' => $utmCampaign,
            'device' => $device,
            'browser' => $browser,
            'os' => $os,
            'country' => $country,
        ];
        $previousPost = null;
        $last = $moment;

        for ($i = 0; $i < $views; $i++) {
            [$type, $postId, $path] = $this->pickPage($pages);
            $local = $moment->modify('+'.($i * mt_rand(40, 240)).' seconds');

            if ($local > $now) {
                break;
            }

            if ($i === 0) {
                $visit += ['entry_path' => $path, 'entry_page_type' => $type, 'entry_blog_id' => $blogId, 'entry_post_id' => $postId];
            }

            $this->queueView($visit, $i, $blogId, $type, $postId, $previousPost, $path, $locale, $local);

            if ($blogId !== null) {
                $this->actOn($visit, $blogId, $postId, $local, $i === 0);
            }

            $previousPost = $postId;
            $last = $local;
        }

        $this->visits[] = $visit + [
            'started_at' => self::utc($moment),
            'last_seen_at' => self::utc($last),
            'page_views' => max(1, $i),
        ];

        if (count($this->events) >= self::BATCH) {
            $this->flush();
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $visit
     */
    private function queueView(
        array $visit,
        int $index,
        ?int $blogId,
        string $type,
        ?int $postId,
        ?int $previousPost,
        string $path,
        string $locale,
        \DateTimeImmutable $local
    ): void {
        $seconds = mt_rand(1, 100) <= 85 ? $this->readingTime($type) : null;
        $props = array_filter([
            'via' => $index > 0 && $type === 'post' && $previousPost !== null && mt_rand(1, 100) <= 25 ? 'related' : null,
        ]);
        if ($type === 'discover' && mt_rand(1, 100) <= 30) {
            $found = mt_rand(1, 100) > 20;
            $props = ['q' => $this->pick($found ? self::SEARCHES : self::EMPTY_SEARCHES), 'search_results' => $found ? mt_rand(1, 40) : 0];
        }
        $viewId = random_bytes(16);
        // Only the first view carries how the visit arrived; the rest are moves inside it.
        $arrival = $index === 0
            ? ['channel' => $visit['channel'], 'referrer_host' => $visit['referrer_host'], 'referrer_source' => $visit['referrer_source']]
            : ['channel' => 'internal', 'referrer_host' => null, 'referrer_source' => null];

        $this->queueEvent([
            'event_key' => $viewId,
            'name' => 'page_view',
            'view_id' => $viewId,
            'visit_id' => $visit['id'],
            'visitor_hash' => $visit['visitor_hash'],
            'visitor_kind' => $visit['visitor_kind'],
            'blog_id' => $blogId,
            'post_id' => $postId,
            'from_post_id' => $index > 0 ? $previousPost : null,
            'page_type' => $type,
            'path' => $path,
            'path_hash' => substr(hash('sha256', $path, true), 0, 8),
            'locale' => $locale,
            'utm_source' => $visit['utm_source'],
            'utm_medium' => $visit['utm_medium'],
            'utm_campaign' => $visit['utm_campaign'],
            'engaged_seconds' => $seconds,
            'scroll_depth' => $seconds === null ? null : min(100, 20 + (int) round($seconds / 3) + mt_rand(0, 20)),
            'engaged_at' => $seconds === null ? null : self::utc($local->modify("+{$seconds} seconds")),
            'props' => $props === [] ? null : $props,
        ] + $arrival + ($index === 0 && $seconds !== null ? $this->vitals() : []), $local);
    }

    /**
     * What a reader did besides reading: goals, shares, and clicks on outbound
     * links and downloads. Without a visit, for history older than the raw window.
     *
     * @param  array<string, mixed>|null  $visit
     * @return int Events queued
     */
    private function actOn(?array $visit, int $blogId, ?int $postId, \DateTimeImmutable $local, bool $firstView): int
    {
        $queued = 0;

        if ($firstView && mt_rand(1, 100) <= self::SUBSCRIBE_PERCENT) {
            $queued += $this->queueAction($visit, 'subscribe', $blogId, null, null, $local);
        }

        if ($postId === null) {
            return $queued;
        }

        foreach (self::GOALS as $goal => $percent) {
            if (mt_rand(1, 100) <= $percent) {
                $queued += $this->queueAction($visit, $goal, $blogId, $postId, null, $local);
            }
        }

        if (mt_rand(1, 100) <= 6) {
            $queued += $this->queueAction($visit, 'outbound', $blogId, $postId, ['host' => $this->pick(self::OUTBOUND)], $local);
        }

        if (mt_rand(1, 100) <= 2) {
            $queued += $this->queueAction($visit, 'share', $blogId, $postId, ['network' => $this->pick(self::SHARES)], $local);
        }

        if (mt_rand(1, 100) <= 2) {
            $queued += $this->queueAction($visit, 'download', $blogId, $postId, ['file' => $this->pick(self::DOWNLOADS)], $local);
        }

        return $queued;
    }

    /**
     * Page speed for a load view, mostly good with a slow tail. No interaction means no INP.
     *
     * @return array{lcp_ms: int, inp_ms: ?int, cls: float, ttfb_ms: int}
     */
    private function vitals(): array
    {
        $slow = mt_rand(1, 100) <= 15;

        return [
            'lcp_ms' => $slow ? mt_rand(2500, 6000) : mt_rand(700, 2500),
            'inp_ms' => mt_rand(1, 100) <= 60 ? ($slow ? mt_rand(200, 700) : mt_rand(40, 200)) : null,
            'cls' => round(($slow ? mt_rand(100, 400) : mt_rand(0, 100)) / 1000, 4),
            'ttfb_ms' => $slow ? mt_rand(800, 2200) : mt_rand(120, 800),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $visit
     * @param  array<string, string>|null  $props
     */
    private function queueAction(?array $visit, string $name, int $blogId, ?int $postId, ?array $props, \DateTimeImmutable $local): int
    {
        $event = ['name' => $name, 'blog_id' => $blogId, 'post_id' => $postId, 'props' => $props];

        if ($visit !== null) {
            $event += [
                'event_key' => in_array($name, self::ONCE_PER_VISIT, true)
                    ? md5($visit['id'].'|'.$name.'|'.$blogId.'|'.($postId ?? ''), true)
                    : random_bytes(16),
                'visit_id' => $visit['id'],
                'visitor_hash' => $visit['visitor_hash'],
                'visitor_kind' => $visit['visitor_kind'],
                'channel' => $visit['channel'],
                'referrer_host' => $visit['referrer_host'],
                'referrer_source' => $visit['referrer_source'],
                'utm_source' => $visit['utm_source'],
                'utm_medium' => $visit['utm_medium'],
                'utm_campaign' => $visit['utm_campaign'],
            ];
        }

        $this->queueEvent($event, $local);

        return 1;
    }

    /**
     * @param  array<string, mixed>  $event  Columns other than the day, hour and time, which $local gives
     */
    private function queueEvent(array $event, \DateTimeImmutable $local): void
    {
        $event += [
            'event_key' => random_bytes(16),
            'local_date' => $local->format('Y-m-d'),
            'local_hour' => (int) $local->format('G'),
            'created_at' => self::utc($local),
        ];
        $event['props'] = isset($event['props']) ? json_encode($event['props'], JSON_THROW_ON_ERROR) : null;

        $this->events[] = $event;
    }

    /**
     * Write the queued visits before their events, in batches.
     */
    private function flush(): void
    {
        foreach (array_chunk($this->visits, self::BATCH) as $rows) {
            $this->insert('analytics_visits', self::VISIT_COLUMNS, $rows, false);
        }

        foreach (array_chunk($this->events, self::BATCH) as $rows) {
            $this->insert('analytics_events', self::EVENT_COLUMNS, $rows, true);
        }

        $this->visits = [];
        $this->events = [];
    }

    /**
     * @param  list<string>  $columns
     * @param  list<array<string, mixed>>  $rows
     */
    private function insert(string $table, array $columns, array $rows, bool $ignoreRepeats): void
    {
        $placeholders = '('.implode(', ', array_fill(0, count($columns), '?')).')';
        $values = [];
        foreach ($rows as $row) {
            foreach ($columns as $column) {
                $values[] = $row[$column] ?? null;
            }
        }

        $this->db->execute(
            'INSERT'.($ignoreRepeats ? ' IGNORE' : '')." INTO {$table} (".implode(', ', $columns).') VALUES '
                .implode(', ', array_fill(0, count($rows), $placeholders)),
            $values
        );
    }

    private static function utc(\DateTimeImmutable $moment): string
    {
        return $moment->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    /**
     * Readers come in the evening more than at dawn.
     */
    private function hour(): int
    {
        return (int) $this->pick([
            6 => 1, 7 => 3, 8 => 5, 9 => 6, 10 => 6, 11 => 6, 12 => 7, 13 => 7, 14 => 6, 15 => 6, 16 => 6,
            17 => 7, 18 => 8, 19 => 9, 20 => 10, 21 => 10, 22 => 7, 23 => 4,
        ]);
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
    private function arrival(?int $blogId): array
    {
        $fromLexicon = mt_rand(1, 100) <= 12 ? $this->lexiconSource($blogId) : null;

        if ($fromLexicon !== null) {
            return ['lexicon', null, $fromLexicon];
        }

        [$channel, $source, $host] = explode('|', $this->pick(self::CHANNELS));

        return [$channel, $host !== '' ? $host : null, $source !== '' ? $source : null];
    }

    /**
     * A platform page or another seeded blog. For the platform's own pages it is always
     * a blog, since moving between them is internal.
     */
    private function lexiconSource(?int $blogId): ?string
    {
        $kind = $blogId === null ? 'blog' : $this->pick(self::LEXICON_ARRIVALS);

        if ($kind !== 'blog') {
            return $kind;
        }

        $others = array_values(array_diff($this->blogIds, [$blogId]));

        return $others === [] ? null : LexiconSource::blog($others[array_rand($others)]);
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
}
