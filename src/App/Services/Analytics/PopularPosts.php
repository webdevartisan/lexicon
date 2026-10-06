<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use App\Models\AnalyticsStatsModel;

/**
 * The few posts readers of a blog opened most over the last 30 days, for the
 * block themes show when the owner switches it on. Cached for an hour: it is
 * the same for every reader and moves slowly.
 */
class PopularPosts
{
    private const DAYS = 30;

    private const LIMIT = 5;

    private const CACHE_TTL = 3600;

    public function __construct(
        private AnalyticsStatsModel $stats,
        private AnalyticsSettings $settings,
    ) {}

    /**
     * @param  array<string, mixed>  $blogSettings  The blog's settings row
     * @return list<array{id: int, title: string, slug: string, views: int}> Empty when the owner hasn't switched it on
     */
    public function forBlog(int $blogId, array $blogSettings): array
    {
        if (empty($blogSettings['analytics_popular_posts']) || !$this->settings->enabled()) {
            return [];
        }

        return fragment()->rememberData(
            "analytics:popular:{$blogId}",
            function () use ($blogId): array {
                $today = new \DateTimeImmutable('today', new \DateTimeZone(blog_timezone($blogId)));

                return $this->stats->popularPosts(
                    $blogId,
                    $today->modify('-'.(self::DAYS - 1).' days')->format('Y-m-d'),
                    $today->format('Y-m-d'),
                    self::LIMIT
                );
            },
            self::CACHE_TTL,
            false
        );
    }
}
