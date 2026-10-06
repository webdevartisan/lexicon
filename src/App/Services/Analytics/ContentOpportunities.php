<?php

declare(strict_types=1);

namespace App\Services\Analytics;

/**
 * Posts worth a second look on the Content page: well-read posts few people
 * find, and much-opened posts few people read. Both are measured against the
 * scope's own posts in the range, so a small blog and a big one each get
 * thresholds that fit them, and the page shows the thresholds it used.
 */
final class ContentOpportunities
{
    /** Below this many views a read rate is mostly chance. */
    public const MIN_VIEWS = 20;

    /** How far a post's read rate has to be from the average to stand out, as a share. */
    public const GAP = 0.15;

    /** Fewer measured posts than this and there is no "most opened quarter" to speak of. */
    private const MIN_POSTS = 4;

    private const LIMIT = 5;

    /**
     * @param  list<array<string, mixed>>  $posts  Top posts rows, each with views and read_views
     * @return array{average: ?float, busyFrom: ?int, quietUpTo: ?int, underread: list<array<string, mixed>>, overlooked: list<array<string, mixed>>}
     */
    public static function find(array $posts): array
    {
        $views = array_sum(array_map(static fn (array $post): int => (int) $post['views'], $posts));
        $average = $views > 0 ? array_sum(array_map(static fn (array $post): int => (int) $post['read_views'], $posts)) / $views : null;
        $measured = array_values(array_filter($posts, static fn (array $post): bool => (int) $post['views'] >= self::MIN_VIEWS));

        $found = ['average' => $average, 'busyFrom' => null, 'quietUpTo' => null, 'underread' => [], 'overlooked' => []];
        if ($average === null || count($measured) < self::MIN_POSTS) {
            return $found;
        }

        $sorted = array_map(static fn (array $post): int => (int) $post['views'], $measured);
        sort($sorted);
        $found['busyFrom'] = $sorted[(int) ceil(0.75 * count($sorted)) - 1];
        $found['quietUpTo'] = $sorted[(int) ceil(0.5 * count($sorted)) - 1];

        foreach ($measured as $post) {
            $rate = (int) $post['read_views'] / (int) $post['views'];
            $post['read_rate'] = $rate;

            if ((int) $post['views'] >= $found['busyFrom'] && $rate <= $average - self::GAP) {
                $found['underread'][] = $post;
            } elseif ((int) $post['views'] <= $found['quietUpTo'] && $rate >= $average + self::GAP) {
                $found['overlooked'][] = $post;
            }
        }

        usort($found['underread'], static fn (array $a, array $b): int => (int) $b['views'] <=> (int) $a['views']);
        usort($found['overlooked'], static fn (array $a, array $b): int => $b['read_rate'] <=> $a['read_rate']);
        $found['underread'] = array_slice($found['underread'], 0, self::LIMIT);
        $found['overlooked'] = array_slice($found['overlooked'], 0, self::LIMIT);

        return $found;
    }
}
