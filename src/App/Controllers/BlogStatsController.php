<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\BlogModel;
use App\Models\BlogSettingsModel;
use App\Services\Analytics\AnalyticsReportService;
use App\Services\Analytics\AnalyticsSettings;
use App\ValueObjects\AnalyticsRange;
use App\ValueObjects\AnalyticsScope;
use Framework\Core\Response;
use Framework\Exceptions\PageNotFoundException;

/**
 * A blog's public stats page, when its owner chose to publish one: views,
 * readers, the most read posts and where readers come from. Nothing about any
 * one reader, and no country breakdown, which small blogs could be traced by.
 */
final class BlogStatsController extends AppController
{
    /** The ranges the page offers. Longer ones need the owner's own dashboard. */
    private const RANGES = ['7d', '30d', '90d'];

    public function __construct(
        private BlogModel $blogs,
        private BlogSettingsModel $blogSettings,
        private AnalyticsReportService $reports,
        private AnalyticsSettings $settings,
    ) {}

    public function show(string $blogSlug): Response
    {
        $blog = $this->blogs->getBlogBySlug($blogSlug);

        if ($blog === null || ($blog['status'] ?? '') !== 'published') {
            throw new PageNotFoundException('Blog not found.');
        }

        $blogId = (int) $blog['id'];
        $blogSettings = $this->blogSettings->findByBlogId($blogId) ?? [];

        if (empty($blogSettings['analytics_public_stats']) || !$this->settings->enabled()) {
            throw new PageNotFoundException('This blog has no public stats page.');
        }

        $preset = (string) ($this->request->get['range'] ?? '30d');
        $range = AnalyticsRange::fromQuery(['range' => in_array($preset, self::RANGES, true) ? $preset : '30d'], blog_timezone($blogId));

        return $this->view('public.BlogStats.show', [
            'blog' => $blog,
            'range' => $range,
            'ranges' => self::RANGES,
            'report' => $this->reports->report(AnalyticsScope::blog($blogId), $range),
        ]);
    }
}
