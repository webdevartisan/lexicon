<?php

declare(strict_types=1);

use App\ValueObjects\TableSort;

/**
 * The ORDER BY the admin tables are built from.
 *
 * Everything here is about what reaches SQL: the request only ever names a key,
 * and rows an admin pinned have to lead the list whatever that key turns out
 * to be.
 */
function sortFor(array $query, string $pinned = ''): TableSort
{
    return TableSort::fromRequest(
        makeRequest('/admin/posts', 'GET', [], $query),
        ['id' => 'p.id', 'title' => 'p.title'],
        defaultKey: 'id',
        defaultDirection: 'desc',
        tiebreaker: 'p.id DESC',
        pinned: $pinned
    );
}

test('the chosen column and direction become the order', function () {
    expect(sortFor(['sort' => 'title', 'dir' => 'asc'])->orderBy())->toBe('p.title ASC, p.id DESC');
});

test('a column that is not on the whitelist falls back to the default', function () {
    expect(sortFor(['sort' => 'p.password', 'dir' => 'asc'])->orderBy())->toBe('p.id DESC, p.id DESC')
        ->and(sortFor(['sort' => 'title', 'dir' => 'asc; DROP TABLE posts'])->orderBy())->toBe('p.title DESC, p.id DESC');
});

test('pinned rows lead the order, whatever the table is sorted by', function () {
    expect(sortFor(['sort' => 'title', 'dir' => 'asc'], 'p.featured_on_home DESC')->orderBy())
        ->toBe('p.featured_on_home DESC, p.title ASC, p.id DESC');
});

test('nothing is pinned unless the page asks for it', function () {
    expect(sortFor(['sort' => 'title', 'dir' => 'asc'])->orderBy())->not->toContain('featured');
});

test('an array sort key cannot fatal the page', function () {
    expect(sortFor(['sort' => ['title'], 'dir' => 'asc'])->orderBy())->toBe('p.id DESC, p.id DESC');
});
