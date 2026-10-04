<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\AppController;
use App\Models\PlatformTrafficModel;
use App\Models\TrafficHitModel;
use App\Services\Traffic\CountryLookup;
use App\Services\Traffic\PlatformTrafficReportService;
use App\Services\Traffic\TrafficSettings;
use App\ValueObjects\TrafficRange;
use Framework\Core\Response;
use Framework\Exceptions\PageNotFoundException;

/**
 * Insights > Traffic in the control panel: the whole platform at a glance,
 * the busiest blogs and posts, and where readers come from.
 *
 * Days are UTC here. Each blog's own page uses the blog's timezone.
 */
class TrafficController extends AppController
{
    protected ?string $areaAbility = 'viewPlatformTraffic';

    private const RIGHT_NOW_MINUTES = 30;

    private const BASE_PATH = '/admin/traffic';

    private const BLOG_COLUMNS = ['blog', 'url', 'status', 'views', 'visitors', 'read_ratio_percent', 'avg_read_seconds', 'bounce_rate_percent'];

    private const POST_COLUMNS = ['post', 'blog', 'url', 'views', 'visitors', 'read_ratio_percent', 'avg_read_seconds'];

    public function __construct(
        private PlatformTrafficReportService $reports,
        private PlatformTrafficModel $traffic,
        private TrafficHitModel $hits,
        private TrafficSettings $settings,
        private CountryLookup $countries,
    ) {}

    public function index(): Response
    {
        $range = TrafficRange::fromQuery($this->request->get, 'UTC');

        return $this->view('traffic.index', [
            'range' => $range,
            'report' => $this->reports->report($range),
            'rightNow' => $this->hits->countRecentEverywhere(self::RIGHT_NOW_MINUTES),
            'today' => (new \DateTimeImmutable('today', new \DateTimeZone('UTC')))->format('Y-m-d'),
            'collectingSince' => $this->traffic->firstDay(),
            'aggregatedAt' => $this->settings->aggregatedAt(),
            'delayed' => $this->settings->aggregationDelayed(),
            'trackingEnabled' => $this->settings->enabled(),
            'aggregationEnabled' => $this->settings->aggregationEnabled(),
            'countriesAvailable' => $this->countries->available(),
            'basePath' => self::BASE_PATH,
            'scope' => 'platform',
            'post' => null,
        ]);
    }

    /**
     * Every blog, every post, or one breakdown for the chosen range as CSV.
     */
    public function export(): Response
    {
        $dimension = (string) ($this->request->get['dimension'] ?? '');
        $range = TrafficRange::fromQuery($this->request->get, 'UTC');
        $filename = "platform-traffic-{$dimension}-{$range->fromDate()}-{$range->toDate()}.csv";

        if ($dimension === 'blogs') {
            return $this->csv($filename, self::BLOG_COLUMNS, $this->blogRows($range));
        }

        if ($dimension === 'posts') {
            return $this->csv($filename, self::POST_COLUMNS, $this->postRows($range));
        }

        if (!in_array($dimension, PlatformTrafficModel::DIMENSIONS, true)) {
            throw new PageNotFoundException('Unknown traffic breakdown.');
        }

        $rows = array_map(
            static fn (array $row): array => [$row['value'], $row['views'], $row['visitors']],
            $this->reports->fullBreakdown($dimension, $range)
        );

        return $this->csv($filename, [$dimension, 'views', 'visitors'], $rows);
    }

    /**
     * @return list<list<string|int|float>>
     */
    private function blogRows(TrafficRange $range): array
    {
        $rows = [];

        foreach ($this->reports->allBlogs($range) as $row) {
            $views = (int) $row['views'];
            $visitors = (int) $row['visitors'];
            $slug = $row['blog_slug'] === null ? null : (string) $row['blog_slug'];

            $rows[] = [
                (string) ($row['blog_name'] ?? '#'.(int) $row['blog_id']),
                $slug !== null ? $this->siteUrl('/blog/'.rawurlencode($slug)) : '',
                (string) ($row['status'] ?? ''),
                $views,
                $visitors,
                self::percentOf((int) $row['read_views'], $views),
                self::averageOf((int) $row['engaged_seconds'], (int) $row['engaged_views']),
                self::percentOf((int) $row['bounces'], $visitors),
            ];
        }

        return $rows;
    }

    /**
     * @return list<list<string|int|float>>
     */
    private function postRows(TrafficRange $range): array
    {
        $rows = [];

        foreach ($this->reports->allPosts($range) as $row) {
            $views = (int) $row['views'];
            $url = '';
            if ($row['title'] !== null && $row['status'] === 'published' && $row['blog_slug'] !== null) {
                $url = $this->siteUrl('/blog/'.rawurlencode((string) $row['blog_slug']).'/'.rawurlencode((string) $row['slug']));
            }

            $rows[] = [
                (string) ($row['title'] ?? chrome_translate('traffic.topPosts.deleted')),
                (string) ($row['blog_name'] ?? ''),
                $url,
                $views,
                (int) $row['visitors'],
                self::percentOf((int) $row['read_views'], $views),
                self::averageOf((int) $row['engaged_seconds'], (int) $row['engaged_views']),
            ];
        }

        return $rows;
    }

    private function siteUrl(string $path): string
    {
        return rtrim(base_url(), '/').lurl($path);
    }

    private static function percentOf(int $part, int $whole): float
    {
        return $whole > 0 ? round($part / $whole * 100, 1) : 0.0;
    }

    private static function averageOf(int $total, int $count): int
    {
        return $count > 0 ? (int) round($total / $count) : 0;
    }
}
