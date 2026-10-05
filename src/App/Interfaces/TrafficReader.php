<?php

declare(strict_types=1);

namespace App\Interfaces;

use App\ValueObjects\TrafficScope;

/**
 * The reads a Traffic page is built from. The daily tables answer them for any
 * range; raw views answer them for a narrowed page inside raw retention.
 * Days are Y-m-d, in the scope's own timezone.
 */
interface TrafficReader
{
    /**
     * @return array<string, int> views, visitors, identified_visitors, returning_visitors, bounces, read_views,
     *                            engaged_views, engaged_seconds, scroll_depth_sum, scroll_25, scroll_50, scroll_75, scroll_100
     */
    public function totals(TrafficScope $scope, string $from, string $to): array;

    /**
     * @return array<string, array{views: int, visitors: int}> Days that had views, keyed Y-m-d
     */
    public function series(TrafficScope $scope, string $from, string $to): array;

    /**
     * @return list<array{value: string, views: int, visitors: int, read_views: int, engaged_views: int, engaged_seconds: int}>
     */
    public function breakdown(TrafficScope $scope, string $dimension, string $from, string $to, int $limit): array;

    /**
     * Posts ranked by views, inside a blog, an author's posts, or the whole site.
     *
     * @return list<array<string, mixed>>
     */
    public function topPosts(TrafficScope $within, string $from, string $to, int $limit): array;

    /**
     * Views by weekday (1 Sunday to 7 Saturday) and hour.
     *
     * @return array<int, array<int, int>>
     */
    public function hourly(TrafficScope $scope, string $from, string $to): array;

    /**
     * How many blogs had a view, over the range and on each day, in UTC days.
     *
     * @return array{total: int, byDay: array<string, int>}
     */
    public function blogsRead(string $from, string $to): array;
}
