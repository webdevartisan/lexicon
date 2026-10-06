<?php

declare(strict_types=1);

namespace App\Services\Analytics;

/**
 * The Insights pages of a blog and of the control panel, in sidebar order, and
 * what opens each. Routes resolve a page name through here, so a name that is
 * not listed is a 404, and the sidebar lists the same pages with the same checks.
 */
final class InsightsPages
{
    /**
     * Blog pages => the BlogPolicy ability that opens the page. A page with fewer
     * than three cards for someone is closed to them: Technical has two for a
     * writer who sees only their own posts, and Authors is empty on a one-author blog.
     */
    public const BLOG = [
        'overview' => 'viewAnalytics',
        'content' => 'viewAnalytics',
        'audience' => 'viewAnalytics',
        'acquisition' => 'viewAnalytics',
        'engagement' => 'viewAnalytics',
        'goals' => 'viewAnalytics',
        'seo' => 'viewAnalytics',
        'authors' => 'viewAuthorInsights',
        'technical' => 'viewAllAnalytics',
    ];

    /** Control panel pages, all opened by view_platform_analytics. */
    public const ADMIN = [
        'overview', 'blogs', 'content', 'audience', 'acquisition', 'engagement', 'goals', 'signups', 'seo',
        'authors', 'technical',
    ];

    /**
     * The glossary terms each page's About panel defines, as analytics.metrics keys.
     * Card tooltips read the same keys, so a term is worded once.
     */
    public const GLOSSARY = [
        'overview' => ['views', 'visitors', 'visits', 'engagedVisits', 'readRatio'],
        'blogs' => ['views', 'visitors', 'readRatio', 'avgRead', 'engagedVisits'],
        'content' => ['views', 'visitors', 'readRatio', 'avgRead'],
        'audience' => ['visitors', 'returning'],
        'acquisition' => ['views', 'visitors', 'visits'],
        'engagement' => ['visits', 'engagedVisits', 'pagesPerVisit', 'visitLength', 'avgRead', 'readRatio', 'readToEnd'],
        'goals' => ['visits', 'goalRate'],
        'seo' => ['searchVisits', 'readRatio', 'avgRead'],
        'authors' => ['views', 'readRatio'],
        'technical' => [],
        'post' => ['views', 'visitors', 'avgRead', 'readRatio', 'readToEnd'],
    ];

    private const ICONS = [
        'overview' => 'layout-dashboard',
        'blogs' => 'book-open',
        'content' => 'file-text',
        'audience' => 'users',
        'acquisition' => 'compass',
        'engagement' => 'book-open-check',
        'goals' => 'target',
        'signups' => 'user-plus',
        'seo' => 'search',
        'authors' => 'pen-line',
        'technical' => 'gauge',
    ];

    private const LABELS = [
        'overview' => 'Overview',
        'blogs' => 'Blogs',
        'content' => 'Content',
        'audience' => 'Audience',
        'acquisition' => 'Acquisition',
        'engagement' => 'Engagement',
        'goals' => 'Goals',
        'signups' => 'Sign-ups',
        'seo' => 'SEO',
        'authors' => 'Authors',
        'technical' => 'Technical',
    ];

    public static function isBlogPage(string $page): bool
    {
        return isset(self::BLOG[$page]);
    }

    public static function isAdminPage(string $page): bool
    {
        return in_array($page, self::ADMIN, true);
    }

    /**
     * The path of a page under its area's Insights root. The root itself resumes
     * where the user left off, so Overview has its own address too.
     */
    public static function path(string $root, string $page): string
    {
        return $root.'/'.$page;
    }

    /**
     * The blog pages as sidebar children, each checked against its own ability.
     *
     * @return list<array<string, mixed>>
     */
    public static function blogNavigation(): array
    {
        $children = [];
        foreach (self::BLOG as $page => $ability) {
            $children[] = self::navItem($page, self::path('/dashboard/blog/{blogId}/insights', $page)) + ['policy' => $ability];
        }

        return $children;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function adminNavigation(): array
    {
        return array_map(
            static fn (string $page): array => self::navItem($page, self::path('/admin/insights', $page)),
            self::ADMIN
        );
    }

    /**
     * @return array<string, string>
     */
    private static function navItem(string $page, string $href): array
    {
        return [
            'label' => self::LABELS[$page],
            'href' => $href,
            'icon' => self::ICONS[$page],
            'key' => 'navigation.insightsPages.'.$page,
        ];
    }
}
