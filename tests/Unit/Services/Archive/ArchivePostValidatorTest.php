<?php

declare(strict_types=1);

use App\Services\Archive\ArchiveMediaUrls;
use App\Services\Archive\ArchivePostValidator;

/**
 * An archive post must never get past checks the editor would apply, and nothing
 * outside the archive format may reach the posts table.
 */
beforeEach(function () {
    $this->validator = new ArchivePostValidator(new ArchiveMediaUrls('https://lexicon.test'));

    $this->post = fn (array $overrides = []): array => array_merge([
        'title' => 'A fine post',
        'slug' => 'a-fine-post',
        'content' => '<p>Hello</p>',
        'status' => 'published',
        'visibility' => 'public',
        'published_at' => '2026-01-01T10:00:00Z',
        'comments_enabled' => true,
        'category' => ['name' => 'Notes', 'slug' => 'notes'],
        'tags' => [['name' => 'One', 'slug' => 'one']],
    ], $overrides);
});

it('accepts a sound post and converts its date to the stored UTC form', function () {
    $result = $this->validator->validate(($this->post)());

    expect($result['errors'])->toBe([])
        ->and($result['data']['published_at'])->toBe('2026-01-01 10:00:00')
        ->and($result['data']['timezone'])->toBe('UTC');
});

it('drops fields the archive format does not define', function () {
    $result = $this->validator->validate(($this->post)(['author_id' => 1, 'is_featured' => 1, 'id' => 9, 'workflow_state' => 'approved']));

    expect($result['errors'])->toBe([])
        ->and($result['data'])->not->toHaveKeys(['author_id', 'is_featured', 'id', 'workflow_state']);
});

it('refuses what the editor refuses', function (array $overrides, string $fragment) {
    $result = $this->validator->validate(($this->post)($overrides));

    expect(implode(' ', $result['errors']))->toContain($fragment);
})->with([
    'moderated status' => [['status' => 'moderated'], 'Unknown status'],
    'unknown visibility' => [['visibility' => 'everyone'], 'Unknown visibility'],
    'bad slug' => [['slug' => 'Bad Slug!'], 'Slug'],
    'long title' => [['title' => str_repeat('t', 101)], 'Title'],
    'long content' => [['content' => str_repeat('a', 60001)], 'Content'],
    'array title' => [['title' => ['x']], 'title field is not text'],
    'bad date' => [['published_at' => 'yesterday'], 'publish date'],
    'script url image' => [['featured_image' => 'javascript:alert(1)'], 'Featured image'],
    'bad tag slug' => [['tags' => [['name' => 'x', 'slug' => '../x']]], 'tag'],
    'too long meta title' => [['meta_title' => str_repeat('m', 71)], 'Meta title'],
]);

it('accepts archive paths, outside addresses and local uploads as images', function (string $value) {
    expect($this->validator->validate(($this->post)(['featured_image' => $value]))['errors'])->toBe([]);
})->with([
    'archive path' => 'media/abc123.jpg',
    'outside' => 'https://images.example.com/a.jpg',
    'local upload' => '/uploads/users/1/blogs/2/media/a.jpg',
]);
