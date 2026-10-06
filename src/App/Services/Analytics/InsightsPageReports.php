<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use App\Interfaces\AnalyticsReader;
use App\Models\AnalyticsDailyEventModel;
use App\Models\AnalyticsEventModel;
use App\Models\AnalyticsPageStatsModel;
use App\Models\AnalyticsStatsModel;
use App\Models\AnalyticsTechnicalModel;
use App\ValueObjects\AnalyticsRange;
use App\ValueObjects\AnalyticsScope;

/**
 * What each Insights page shows, one builder per page, each cached on its own
 * for five minutes. The key names the page, scope, range, comparison and
 * filters, never the viewer. A page name must already be checked against
 * InsightsPages.
 */
class InsightsPageReports
{
    private const CACHE_TTL = 300;

    private const LIST_LIMIT = 10;

    /** Overview blocks are previews of their own pages. */
    private const PREVIEW_LIMIT = 5;

    /** Posts read per author, to group the site's or a blog's posts by who wrote them. */
    private const AUTHOR_POSTS_LIMIT = 2000;

    private const TOP_POSTS_PER_AUTHOR = 3;

    /** Months shown in each author's publishing row. */
    private const PUBLISHING_MONTHS = 6;

    /** Posts read in a range, for finding the ones that stand out. */
    private const OPPORTUNITY_POSTS = 500;

    /** Days after publishing the Content page follows a post for. */
    private const AGE_DAYS = 30;

    /** Measured views a page needs before it can be called one of the slowest. */
    private const SLOW_PAGE_MIN_SAMPLES = 10;

    /** Breakdown cards per page; a scope that lacks one leaves it out. */
    private const BREAKDOWNS = [
        'overview' => ['source', 'lexicon', 'channel'],
        'content' => ['page', 'category', 'tag'],
        'audience' => ['country', 'locale', 'device', 'browser', 'os'],
        'acquisition' => ['channel', 'source', 'lexicon', 'utm_campaign', 'utm_source', 'utm_medium', 'entry', 'exit'],
        'engagement' => ['channel', 'next', 'related'],
        'seo' => ['source', 'channel', 'search_entry'],
        'authors' => ['author'],
    ];

    public function __construct(
        private AnalyticsReportService $reports,
        private AnalyticsStatsModel $stats,
        private AnalyticsPageStatsModel $pageStats,
        private AnalyticsDailyEventModel $events,
        private AnalyticsEventModel $rawEvents,
        private ReferrerClassifier $referrers,
        private AnalyticsTechnicalModel $technical,
        private SeoHealth $seoHealth,
    ) {}

    /**
     * @param  array<string, string>  $filters  Already checked with AnalyticsReportService::filtersFrom()
     * @return array<string, mixed>
     */
    public function page(string $page, AnalyticsScope $scope, AnalyticsRange $range, array $filters = []): array
    {
        ksort($filters);
        $filterKey = $filters === [] ? '' : ':'.md5((string) json_encode($filters));

        return fragment()->rememberData(
            "insights:{$page}:{$scope->key()}:{$range->key()}{$filterKey}",
            fn (): array => $this->build($page, $scope, $range, $filters),
            self::CACHE_TTL,
            false
        );
    }

    /**
     * @param  array<string, string>  $filters
     * @return array<string, mixed>
     */
    private function build(string $page, AnalyticsScope $scope, AnalyticsRange $range, array $filters): array
    {
        $reader = $this->reports->reader($filters);
        $limit = $page === 'overview' ? self::PREVIEW_LIMIT : self::LIST_LIMIT;
        $report = [
            'breakdowns' => $this->reports->breakdowns($reader, $scope, $range, self::BREAKDOWNS[$page] ?? [], $limit),
        ];

        return $report + match ($page) {
            'overview' => $this->overview($reader, $scope, $range),
            'content' => $this->content($reader, $scope, $range),
            'audience' => [
                'metrics' => $this->reports->metrics($reader, $scope, $range),
                'hourly' => $reader->hourly($scope, $range->fromDate(), $range->toDate()),
            ],
            'engagement' => [
                'metrics' => $this->reports->metrics($reader, $scope, $range),
                'reactions' => $this->events->eventTotals($scope, ['like', 'dislike'], $range->fromDate(), $range->toDate()),
            ],
            'goals' => $this->goals($reader, $scope, $range),
            'seo' => $this->seo($reader, $scope, $range, $report['breakdowns']),
            'authors' => $this->authors($scope, $range, $report['breakdowns']['author'] ?? []),
            'technical' => $this->technical($scope, $range),
            'blogs' => $this->blogs($reader, $range),
            default => [],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function overview(AnalyticsReader $reader, AnalyticsScope $scope, AnalyticsRange $range): array
    {
        return [
            'metrics' => $this->reports->metrics($reader, $scope, $range),
            'series' => $this->reports->series($reader, $scope, $range),
            'interval' => AnalyticsReportService::interval($range),
            'markers' => $this->reports->markers($scope, $range),
            'topPosts' => $this->topPosts($reader, $scope, $range, self::PREVIEW_LIMIT),
            'goals' => $this->events->goalCounts($scope, $range->fromDate(), $range->toDate()),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function topPosts(AnalyticsReader $reader, AnalyticsScope $scope, AnalyticsRange $range, int $limit): array
    {
        return $scope->ranksPosts() ? $reader->topPosts($scope, $range->fromDate(), $range->toDate(), $limit) : [];
    }

    /**
     * Top posts, posts that stand out from the rest, and how views follow publishing.
     * The curve covers posts published in the year before the range ends, once counting began.
     *
     * @return array<string, mixed>
     */
    private function content(AnalyticsReader $reader, AnalyticsScope $scope, AnalyticsRange $range): array
    {
        if (!$scope->ranksPosts()) {
            return ['topPosts' => [], 'opportunities' => null, 'byAge' => null];
        }

        $to = $range->toDate();
        $yearBefore = (new \DateTimeImmutable($to))->modify('-1 year')->format('Y-m-d');
        $since = max($yearBefore, $this->stats->firstDay($scope) ?? $to);

        return [
            'topPosts' => $this->topPosts($reader, $scope, $range, self::LIST_LIMIT),
            'opportunities' => ContentOpportunities::find($reader->topPosts($scope, $range->fromDate(), $to, self::OPPORTUNITY_POSTS)),
            'byAge' => ['since' => $since] + $this->pageStats->viewsByAge($scope, $since, $to, self::AGE_DAYS),
        ];
    }

    /**
     * Each goal with its rate per visit, goals by how the visit began, and link clicks.
     *
     * @return array<string, mixed>
     */
    private function goals(AnalyticsReader $reader, AnalyticsScope $scope, AnalyticsRange $range): array
    {
        $from = $range->fromDate();
        $to = $range->toDate();

        return [
            'goals' => $this->events->goalCounts($scope, $from, $to),
            'goalRates' => $this->goalRates($reader, $scope, $range),
            'goalsByChannel' => $this->events->goalBreakdown($scope, 'channel', $from, $to, self::LIST_LIMIT),
            'goalsBySource' => $this->events->goalBreakdown($scope, 'source', $from, $to, self::LIST_LIMIT),
            'outbound' => $this->events->clickBreakdown('outbound', $scope, $from, $to, self::LIST_LIMIT),
            'downloads' => $this->events->clickBreakdown('download', $scope, $from, $to, self::LIST_LIMIT),
            'shares' => $this->events->clickBreakdown('share', $scope, $from, $to, self::LIST_LIMIT),
        ];
    }

    /**
     * Goals per visit, counted only from the first day the scope has visits, so
     * goals from before visits were kept don't inflate the rate.
     *
     * @return array{from: string, visits: int, rates: array<string, ?float>}|null Null when the range has no visits
     */
    private function goalRates(AnalyticsReader $reader, AnalyticsScope $scope, AnalyticsRange $range): ?array
    {
        $since = $this->pageStats->visitsSince($scope);
        if ($since === null || $since > $range->toDate()) {
            return null;
        }

        $from = max($range->fromDate(), $since);
        $visits = (int) $reader->totals($scope, $from, $range->toDate())['visits'];
        $rates = [];
        foreach ($this->events->goalCounts($scope, $from, $range->toDate()) as $goal => $reached) {
            $rates[$goal] = AnalyticsReportService::ratio($reached, $visits);
        }

        return ['from' => $from, 'visits' => $visits, 'rates' => $rates];
    }

    /**
     * Search traffic over time, the engines it came from, where it landed, how
     * search readers read compared with everyone, and the posts' search health.
     *
     * @param  array<string, list<array<string, mixed>>>  $breakdowns
     * @return array<string, mixed>
     */
    private function seo(AnalyticsReader $reader, AnalyticsScope $scope, AnalyticsRange $range, array $breakdowns): array
    {
        $previous = $range->previous();
        $engines = $this->referrers->sourcesIn('search');
        $search = null;
        foreach ($breakdowns['channel'] ?? [] as $row) {
            if ($row['value'] === 'search') {
                $search = $row;
            }
        }

        return [
            'searchSeries' => AnalyticsReportService::seriesFrom(
                $this->pageStats->channelSeries($scope, 'search', $range->fromDate(), $range->toDate()),
                $this->pageStats->channelSeries($scope, 'search', $previous->fromDate(), $previous->toDate()),
                $range
            ),
            'interval' => AnalyticsReportService::interval($range),
            'markers' => [],
            'searchEngines' => array_values(array_filter(
                $breakdowns['source'] ?? [],
                static fn (array $row): bool => in_array($row['value'], $engines, true)
            )),
            'search' => $search,
            'everyone' => $reader->totals($scope, $range->fromDate(), $range->toDate()),
            'health' => match ($scope->type) {
                AnalyticsScope::BLOG => $this->seoHealth->forBlog((int) $scope->blogId),
                AnalyticsScope::POST => $this->seoHealth->forBlog((int) $scope->blogId, $scope->ids),
                default => null,
            },
            'site' => $scope->type === AnalyticsScope::SITE ? $this->seoHealth->forSite() : null,
        ];
    }

    /**
     * Each author's posts, readers and goals in the range, their best posts, and
     * how often they publish. On the site, also the writers who started publishing.
     *
     * @param  list<array<string, mixed>>  $authorRows  The author breakdown, for visitors on a blog
     * @return array<string, mixed>
     */
    private function authors(AnalyticsScope $scope, AnalyticsRange $range, array $authorRows): array
    {
        $from = $range->fromDate();
        $to = $range->toDate();
        $blogId = $scope->type === AnalyticsScope::BLOG ? (int) $scope->blogId : null;
        $within = $blogId === null ? AnalyticsScope::site() : $scope;
        $visitors = array_column($authorRows, 'visitors', 'value');
        $published = $this->pageStats->postsPublished($blogId, $from, $to);
        $goals = $this->events->goalsByAuthor($blogId, $from, $to);

        $authors = [];
        foreach ($this->stats->topPosts($within, $from, $to, self::AUTHOR_POSTS_LIMIT) as $post) {
            if ($post['author_id'] === null) {
                continue;
            }

            $id = (int) $post['author_id'];
            $authors[$id] ??= ['author_id' => $id, 'views' => 0, 'read_views' => 0, 'posts_read' => 0, 'top_posts' => []];
            $authors[$id]['views'] += (int) $post['views'];
            $authors[$id]['read_views'] += (int) $post['read_views'];
            $authors[$id]['posts_read']++;
            if (count($authors[$id]['top_posts']) < self::TOP_POSTS_PER_AUTHOR) {
                $authors[$id]['top_posts'][] = $post;
            }
        }

        // Someone who published lately but wasn't read in the range still has a publishing row.
        $publishing = $this->pageStats->publishingByMonth($blogId, $to, self::PUBLISHING_MONTHS);
        foreach ([...array_keys($published), ...array_keys($publishing)] as $id) {
            $authors[$id] ??= ['author_id' => $id, 'views' => 0, 'read_views' => 0, 'posts_read' => 0, 'top_posts' => []];
        }

        $names = $this->stats->labels('author', array_keys($authors));
        foreach ($authors as $id => &$author) {
            $author += [
                'name' => $names[$id] ?? null,
                'visitors' => $blogId === null ? null : (int) ($visitors[(string) $id] ?? 0),
                'published' => $published[$id] ?? 0,
                'goals' => $goals[$id] ?? 0,
            ];
        }
        unset($author);

        usort($authors, static fn (array $a, array $b): int => [$b['views'], $b['published']] <=> [$a['views'], $a['published']]);

        return [
            'authors' => $authors,
            'publishing' => $publishing,
            'newWriters' => $blogId === null ? $this->namedWriters($this->pageStats->newWriters($from, $to, self::LIST_LIMIT)) : [],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $writers
     * @return list<array<string, mixed>>
     */
    private function namedWriters(array $writers): array
    {
        $names = $this->stats->labels('author', array_column($writers, 'author_id'));

        return array_map(static fn (array $row): array => $row + ['name' => $names[$row['author_id']] ?? null], $writers);
    }

    /**
     * Missing pages and page speed; on the whole site, server errors instead of missing pages.
     * Page speed always covers the last PageSpeed::WINDOW_DAYS days, whatever the range.
     *
     * @return array<string, mixed>
     */
    private function technical(AnalyticsScope $scope, AnalyticsRange $range): array
    {
        $isSite = $scope->type === AnalyticsScope::SITE;

        return [
            'missing' => $this->missing($scope, $range),
            'speed' => $this->technical->speedByPageType($scope, PageSpeed::WINDOW_DAYS),
            'slowest' => $this->technical->slowestPages($scope, PageSpeed::WINDOW_DAYS, self::SLOW_PAGE_MIN_SAMPLES, self::LIST_LIMIT),
            'serverErrors' => $isSite ? $this->technical->serverErrors($range->fromDate(), $range->toDate(), self::LIST_LIMIT) : [],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function missing(AnalyticsScope $scope, AnalyticsRange $range): array
    {
        return match ($scope->type) {
            AnalyticsScope::BLOG => $this->rawEvents->topMissing((int) $scope->blogId, $range->fromDate(), $range->toDate(), self::LIST_LIMIT),
            AnalyticsScope::PLATFORM => $this->rawEvents->topMissing(null, $range->fromDate(), $range->toDate(), self::LIST_LIMIT),
            default => [],
        };
    }

    /**
     * The control panel's Blogs page: blogs read over time and the busiest blogs.
     *
     * @return array<string, mixed>
     */
    private function blogs(AnalyticsReader $reader, AnalyticsRange $range): array
    {
        $site = AnalyticsScope::site();

        return [
            'series' => $this->reports->series($reader, $site, $range),
            'interval' => AnalyticsReportService::interval($range),
            'markers' => [],
            'blogsRead' => $reader->blogsRead($range->fromDate(), $range->toDate())['total'],
            'topBlogs' => $this->reports->topBlogs($range, 25),
        ];
    }
}
