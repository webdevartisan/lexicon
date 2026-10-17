<?php

declare(strict_types=1);

use App\Resources\BlogResource;
use App\Resources\PostResource;
use App\Services\BlogCacheInvalidator;
use App\Services\CommentCacheInvalidator;
use App\Services\PostCacheInvalidator;
use Framework\Cache\CacheService;
use Framework\Cache\FragmentCache;
use Framework\Core\App;

beforeEach(function () {
    $this->originalFragments = App::container()->get(FragmentCache::class);
    $this->originalCache = App::container()->get(CacheService::class);
    $this->forgotten = new ArrayObject();
    $this->patterns = new ArrayObject();

    $cache = Mockery::mock(CacheService::class);
    $cache->shouldReceive('deletePattern')->andReturnUsing(function (string $pattern): int {
        $this->patterns[] = $pattern;

        return 0;
    });
    App::container()->set(CacheService::class, fn () => $cache);

    $fragments = Mockery::mock(FragmentCache::class);
    $fragments->shouldReceive('forget')->andReturnUsing(function (string $key, bool $localized = true): bool {
        $this->forgotten[] = [$key, $localized];

        return true;
    });

    App::container()->set(FragmentCache::class, fn () => $fragments);
});

afterEach(function () {
    $fragments = $this->originalFragments;
    $cache = $this->originalCache;
    App::container()->set(FragmentCache::class, fn () => $fragments);
    App::container()->set(CacheService::class, fn () => $cache);
});

test('forgetting a post\'s tags drops its tag list in every locale', function () {
    (new PostCacheInvalidator())->forgetTags(42);

    expect($this->forgotten->getArrayCopy())->toBe([['post-tags:42', false]]);
});

test('forgetting a blog\'s settings drops its settings row in every locale', function () {
    (new BlogCacheInvalidator())->forgetSettings(7);

    expect($this->forgotten->getArrayCopy())->toBe([['blog-settings:7', false]]);
});

test('an edited category clears Discover and the category list of the blog', function () {
    (new BlogCacheInvalidator())->categoryChanged(2);

    expect($this->patterns->getArrayCopy())->toBe(['*:GET:/discover*'])
        ->and($this->forgotten->getArrayCopy())->toBe([['blog-pubcats:2', false]]);
});

test('a deleted category clears every blog page and the category list of the blog', function () {
    (new BlogCacheInvalidator())->categoryRemoved(2);

    expect($this->patterns->getArrayCopy())->toBe(['*:GET:/blog*'])
        ->and($this->forgotten->getArrayCopy())->toBe([['blog-pubcats:2', false]]);
});

test('a category with no known blog skips the category list', function () {
    (new BlogCacheInvalidator())->categoryChanged(0);

    expect($this->forgotten->getArrayCopy())->toBe([]);
});

test('forgetting a blog clears its home page, its subpages and the cached row', function () {
    (new BlogCacheInvalidator())->forgetBlog('field-notes');

    expect($this->patterns->getArrayCopy())->toBe([
        '*:GET:/blog/field-notes',
        '*:GET:/blog/field-notes?*',
        '*:GET:/blog/field-notes/*',
    ])->and($this->forgotten->getArrayCopy())->toBe([['blog-by-slug:field-notes', false]]);
});

test('a renamed blog clears both slugs and Discover', function () {
    (new BlogCacheInvalidator())->blogChanged('old-notes', 'new-notes');

    expect($this->patterns->getArrayCopy())
        ->toContain('*:GET:/blog/old-notes', '*:GET:/blog/new-notes', '*:GET:/discover*')
        ->and($this->forgotten->getArrayCopy())
        ->toBe([['blog-by-slug:old-notes', false], ['blog-by-slug:new-notes', false]]);
});

test('an edit that keeps the slug clears it once', function (?string $newSlug) {
    (new BlogCacheInvalidator())->blogChanged('field-notes', $newSlug);

    expect($this->forgotten->getArrayCopy())->toBe([['blog-by-slug:field-notes', false]])
        ->and($this->patterns->getArrayCopy())->toHaveCount(4);
})->with([
    'no slug given' => [null],
    'same slug' => ['field-notes'],
]);

test('a deleted blog clears its pages and Discover', function () {
    (new BlogCacheInvalidator())->blogRemoved('field-notes');

    expect($this->patterns->getArrayCopy())->toHaveCount(4)
        ->and($this->patterns->getArrayCopy())->toContain('*:GET:/discover*');
});

test('a new blog only drops the cached lookup for its slug', function () {
    (new BlogCacheInvalidator())->blogCreated('field-notes');

    expect($this->patterns->getArrayCopy())->toBe([])
        ->and($this->forgotten->getArrayCopy())->toBe([['blog-by-slug:field-notes', false]]);
});

test('forgetting a comment thread drops every sort order of it', function () {
    (new CommentCacheInvalidator())->forgetThread(12);

    expect($this->forgotten->getArrayCopy())->toBe([
        ['post-comments:12:top', false],
        ['post-comments:12:new', false],
    ]);
});

test('a new post clears Discover and the blog-wide post lists', function () {
    $fragments = App::container()->get(FragmentCache::class);
    $fragments->shouldReceive('forgetPattern')->andReturnUsing(function (string $pattern): int {
        $this->forgotten[] = [$pattern, 'pattern'];

        return 0;
    });

    (new PostCacheInvalidator())->postCreated(4);

    expect($this->patterns->getArrayCopy())->toBe(['*:GET:/discover*'])
        ->and($this->forgotten->getArrayCopy())->toBe([
            ['post-nav:4:*', 'pattern'],
            ['post-related:4:*', 'pattern'],
            ['blog-featured:4', false],
            ['blog-posts:4:*', 'pattern'],
            ['blog-catposts:4:*', 'pattern'],
            ['blog-pubcats:4', false],
        ]);
});

test('a changed post clears its page, a new slug, Discover and its own fragments', function () {
    $fragments = App::container()->get(FragmentCache::class);
    $fragments->shouldReceive('forgetPattern')->andReturn(0);

    $blog = Mockery::mock(BlogResource::class);
    $blog->shouldReceive('slug')->andReturn('field-notes');
    $blog->shouldReceive('id')->andReturn(4);
    $post = Mockery::mock(PostResource::class);
    $post->shouldReceive('blog')->andReturn($blog);
    $post->shouldReceive('slug')->andReturn('first-frost');
    $post->shouldReceive('id')->andReturn(90);

    (new PostCacheInvalidator())->postChanged($post, 'hard-frost');

    expect($this->patterns->getArrayCopy())->toBe([
        '*:GET:/blog/field-notes/first-frost*',
        '*:GET:/blog/field-notes/hard-frost*',
        '*:GET:/discover*',
    ])->and($this->forgotten->getArrayCopy())
        ->toContain(['post-tags:90', false], ['post-comments:90:top', false], ['post-comments:90:new', false])
        ->not->toContain(['post-comments:90', false]);
});
