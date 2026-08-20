<?php

declare(strict_types=1);

namespace App\Traits;

/**
 * Paginated post lists built from a per-user engagement table.
 *
 * Saved and Liked are the same page with a different join table behind them,
 * so the query lives here once. Both order by the engagement row rather than
 * the post: the reader put things on the list in an order, and that order is
 * what the list is for.
 */
trait ListsEngagedPosts
{
    /**
     * One page of published posts the user engaged with, newest first.
     *
     * Every display field the row needs is selected here; nothing is fetched
     * per row afterwards. Unpublished posts and blogs drop out of the join
     * rather than being filtered in PHP, so the count and the page agree.
     *
     * @param  int  $userId  Owner of the list
     * @param  int  $page  1-based page index
     * @param  int  $perPage  Rows per page
     * @param  string  $extraWhere  Additional condition on the engagement row, e.g. 'e.value = 1'
     * @param  array<int, mixed>  $extraBindings  Bindings for $extraWhere
     * @return array{items: array<int, array<string, mixed>>, total: int, page: int, perPage: int}
     */
    protected function pageOfEngagedPosts(
        int $userId,
        int $page,
        int $perPage,
        string $extraWhere = '',
        array $extraBindings = []
    ): array {
        $perPage = max(1, min(100, $perPage));
        $page = max(1, $page);
        $offset = ($page - 1) * $perPage;

        $where = "e.user_id = ?
                  AND p.status = 'published' AND p.visibility = 'public'
                  AND b.status = 'published'";

        if ($extraWhere !== '') {
            $where .= ' AND '.$extraWhere;
        }

        $from = "FROM {$this->getTable()} e
                 INNER JOIN posts p ON p.id = e.post_id
                 INNER JOIN blogs b ON b.id = p.blog_id
                 WHERE {$where}";

        $bindings = array_merge([$userId], $extraBindings);

        $total = (int) $this->database
            ->query("SELECT COUNT(*) {$from}", $bindings)
            ->fetchColumn();

        // LIMIT and OFFSET are cast ints, never user text; every value that
        // came from the request is bound.
        $items = $this->database
            ->query(
                "SELECT p.id, p.title, p.slug, p.excerpt, p.featured_image, p.published_at,
                        b.id AS blog_id, b.blog_name, b.blog_slug,
                        e.created_at AS engaged_at
                 {$from}
                 ORDER BY e.created_at DESC, e.id DESC
                 LIMIT ".(int) $perPage.' OFFSET '.(int) $offset,
                $bindings
            )
            ->fetchAll(\PDO::FETCH_ASSOC);

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
        ];
    }
}
