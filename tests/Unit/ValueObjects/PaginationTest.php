<?php

declare(strict_types=1);

use App\ValueObjects\Pagination;

/**
 * The numbers behind the paginator.
 */
test('a middle page knows both neighbours', function () {
    expect(Pagination::meta(50, 10, 3))->toBe([
        'current_page' => 3,
        'per_page' => 10,
        'total_records' => 50,
        'total_pages' => 5,
        'has_previous' => true,
        'has_next' => true,
    ]);
});

test('a page past the end lands on the last page', function () {
    expect(Pagination::meta(25, 10, 99))->toMatchArray(['current_page' => 3, 'has_next' => false]);
});

test('a page below one lands on the first page', function () {
    expect(Pagination::meta(25, 10, -4))->toMatchArray(['current_page' => 1, 'has_previous' => false]);
});

test('an empty list still has one page', function () {
    expect(Pagination::meta(0, 10, 1))
        ->toMatchArray(['total_pages' => 1, 'has_next' => false, 'has_previous' => false]);
});
