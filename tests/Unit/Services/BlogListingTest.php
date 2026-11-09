<?php

declare(strict_types=1);

use App\Services\BlogListing;
use App\ValueObjects\TableSort;

/**
 * The Blogs list: search, status and role filters, sort order and paging.
 */
function listedBlog(int $id, string $name, string $role = 'owner', string $status = 'published', int $posts = 0): array
{
    return [
        'id' => $id,
        'blog_name' => $name,
        'blog_slug' => strtolower(str_replace(' ', '-', $name)),
        'description' => '',
        'user_role' => $role,
        'status' => $status,
        'post_count' => $posts,
        'author_count' => 1,
        'updated_at' => sprintf('2026-01-%02d 10:00:00', $id),
        'created_at' => sprintf('2026-01-%02d 09:00:00', $id),
    ];
}

function listingSort(array $query = []): TableSort
{
    return TableSort::fromRequest(
        makeRequest('/dashboard/blog', 'GET', [], $query),
        BlogListing::SORT_FIELDS,
        defaultKey: 'updated',
        defaultDirection: 'desc'
    );
}

function listPage(array $blogs, array $filters = [], array $sortQuery = [], int $page = 1): array
{
    $filters += ['q' => '', 'status' => '', 'role' => ''];

    return (new BlogListing())->page($blogs, $filters, listingSort($sortQuery), $page);
}

function listedNames(array $page): array
{
    return array_column($page['rows'], 'blog_name');
}

test('the most recently updated blog comes first by default', function () {
    $blogs = [listedBlog(1, 'Old'), listedBlog(3, 'New'), listedBlog(2, 'Middle')];

    expect(listedNames(listPage($blogs)))->toBe(['New', 'Middle', 'Old']);
});

test('a column sorts in the direction asked for', function () {
    $blogs = [listedBlog(1, 'Few', posts: 2), listedBlog(2, 'Many', posts: 9)];

    expect(listedNames(listPage($blogs, sortQuery: ['sort' => 'posts', 'dir' => 'asc'])))->toBe(['Few', 'Many'])
        ->and(listedNames(listPage($blogs, sortQuery: ['sort' => 'posts', 'dir' => 'desc'])))->toBe(['Many', 'Few']);
});

test('search matches the name or the address, ignoring case', function () {
    $blogs = [listedBlog(1, 'Garden Notes'), listedBlog(2, 'Travel Log')];

    expect(listedNames(listPage($blogs, ['q' => 'GARDEN'])))->toBe(['Garden Notes'])
        ->and(listedNames(listPage($blogs, ['q' => 'travel-log'])))->toBe(['Travel Log']);
});

test('the status filter narrows the rows but the counts still show every status', function () {
    $blogs = [
        listedBlog(1, 'Live'),
        listedBlog(2, 'Wip', status: 'draft'),
        listedBlog(3, 'Old', status: 'archived'),
    ];

    $page = listPage($blogs, ['status' => 'draft']);

    expect(listedNames($page))->toBe(['Wip'])
        ->and($page['counts'])->toBe(['published' => 1, 'draft' => 1, 'archived' => 1, 'all' => 3]);
});

test('an unknown status is ignored', function () {
    $blogs = [listedBlog(1, 'Live'), listedBlog(2, 'Wip', status: 'draft')];

    expect(listPage($blogs, ['status' => 'bogus'])['rows'])->toHaveCount(2);
});

test('the role filter separates owned blogs from shared ones', function () {
    $blogs = [listedBlog(1, 'Mine'), listedBlog(2, 'Theirs', role: 'editor')];

    expect(listedNames(listPage($blogs, ['role' => 'owned'])))->toBe(['Mine'])
        ->and(listedNames(listPage($blogs, ['role' => 'shared'])))->toBe(['Theirs'])
        ->and(listPage($blogs)['hasShared'])->toBeTrue()
        ->and(listPage([$blogs[0]])['hasShared'])->toBeFalse();
});

test('rows are paged and an out of range page lands on the last one', function () {
    $blogs = [];
    for ($i = 1; $i <= BlogListing::PER_PAGE + 3; $i++) {
        $blogs[] = listedBlog($i, 'Blog '.$i);
    }

    $first = listPage($blogs);
    $beyond = listPage($blogs, page: 99);

    expect($first['rows'])->toHaveCount(BlogListing::PER_PAGE)
        ->and($first['pagination'])->toMatchArray(['current_page' => 1, 'total_pages' => 2, 'has_next' => true])
        ->and($beyond['rows'])->toHaveCount(3)
        ->and($beyond['pagination'])->toMatchArray(['current_page' => 2, 'has_next' => false, 'has_previous' => true]);
});

test('blogs that tie on the sort value keep a stable order', function () {
    $blogs = [listedBlog(1, 'A', posts: 5), listedBlog(2, 'B', posts: 5), listedBlog(3, 'C', posts: 5)];

    expect(listedNames(listPage($blogs, sortQuery: ['sort' => 'posts', 'dir' => 'desc'])))->toBe(['C', 'B', 'A']);
});
