<?php

declare(strict_types=1);

namespace App\Services;

use App\ValueObjects\Pagination;
use App\ValueObjects\TableSort;

/**
 * Filters, sorts and pages the blogs a user can reach for the Blogs list.
 *
 * A user's blogs are few, so this works on the rows already loaded instead of
 * asking SQL for a filtered page.
 */
final class BlogListing
{
    public const PER_PAGE = 12;

    /** Sortable column key => blog row field. */
    public const SORT_FIELDS = [
        'name' => 'blog_name',
        'role' => 'user_role',
        'status' => 'status',
        'posts' => 'post_count',
        'team' => 'author_count',
        'updated' => 'updated_at',
        'created' => 'created_at',
    ];

    private const STATUSES = ['draft', 'published', 'archived'];

    /**
     * One page of blogs plus what the toolbar needs to describe it.
     *
     * @param  array<int, array<string, mixed>>  $blogs  Rows from BlogModel::getAccessibleBlogs()
     * @param  array{q: string, status: string, role: string}  $filters  Search text, a status, and
     *                          `owned`, `shared` or empty for the role; empty means no filter
     * @return array{rows: array<int, array<string, mixed>>, pagination: array<string, mixed>,
     *               counts: array<string, int>, hasShared: bool}
     */
    public function page(array $blogs, array $filters, TableSort $sort, int $page): array
    {
        $matching = $this->filter($blogs, $filters['q'], $filters['role']);

        // Counts ignore the status filter, so each status option shows what picking it would give.
        $counts = array_count_values(array_column($matching, 'status')) + array_fill_keys(self::STATUSES, 0);
        $counts['all'] = count($matching);

        $status = $filters['status'];
        $visible = in_array($status, self::STATUSES, true) ? $this->withStatus($matching, $status) : $matching;
        $visible = $this->sorted($visible, $sort);

        $pagination = Pagination::meta(count($visible), self::PER_PAGE, $page);
        $offset = ($pagination['current_page'] - 1) * self::PER_PAGE;

        return [
            'rows' => array_slice($visible, $offset, self::PER_PAGE),
            'pagination' => $pagination,
            'counts' => $counts,
            'hasShared' => count(array_keys(array_column($blogs, 'user_role'), 'owner', true)) < count($blogs),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $blogs
     * @return array<int, array<string, mixed>>
     */
    private function filter(array $blogs, string $q, string $role): array
    {
        $needle = mb_strtolower($q);

        return array_values(array_filter($blogs, static function (array $b) use ($needle, $role): bool {
            if ($role !== '' && ($b['user_role'] === 'owner') !== ($role === 'owned')) {
                return false;
            }

            return $needle === ''
                || str_contains(mb_strtolower((string) $b['blog_name']), $needle)
                || str_contains(mb_strtolower((string) $b['blog_slug']), $needle)
                || str_contains(mb_strtolower((string) ($b['description'] ?? '')), $needle);
        }));
    }

    /**
     * @param  array<int, array<string, mixed>>  $blogs
     * @return array<int, array<string, mixed>>
     */
    private function withStatus(array $blogs, string $status): array
    {
        return array_values(array_filter($blogs, static function (array $b) use ($status): bool {
            return $b['status'] === $status;
        }));
    }

    /**
     * @param  array<int, array<string, mixed>>  $blogs
     * @return array<int, array<string, mixed>>
     */
    private function sorted(array $blogs, TableSort $sort): array
    {
        $field = self::SORT_FIELDS[$sort->key()];
        $direction = $sort->direction() === 'asc' ? 1 : -1;

        usort($blogs, static function (array $a, array $b) use ($field, $direction): int {
            $order = in_array($field, ['post_count', 'author_count'], true)
                ? (int) $a[$field] <=> (int) $b[$field]
                : strcasecmp((string) $a[$field], (string) $b[$field]);

            // Equal values fall back to the id so rows cannot swap places between pages.
            return $order !== 0 ? $order * $direction : (int) $b['id'] <=> (int) $a['id'];
        });

        return $blogs;
    }
}
