<?php

declare(strict_types=1);

namespace App\ValueObjects;

/**
 * The paging numbers the paginator component reads.
 */
final class Pagination
{
    /**
     * Work out which page to show and the details around it.
     *
     * A page past the end lands on the last one, and an empty list still has one page.
     *
     * @param  int  $total  How many rows match
     * @param  int  $perPage  Rows on a full page
     * @param  int  $page  The page asked for
     * @return array{current_page: int, per_page: int, total_records: int, total_pages: int,
     *               has_previous: bool, has_next: bool}
     */
    public static function meta(int $total, int $perPage, int $page): array
    {
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $totalPages);

        return [
            'current_page' => $page,
            'per_page' => $perPage,
            'total_records' => $total,
            'total_pages' => $totalPages,
            'has_previous' => $page > 1,
            'has_next' => $page < $totalPages,
        ];
    }
}
