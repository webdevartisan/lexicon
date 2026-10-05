<?php

declare(strict_types=1);

use App\Services\PublicCacheInvalidator;
use Framework\Cache\CacheService;
use Framework\Core\App;

beforeEach(function () {
    $this->originalCache = App::container()->get(CacheService::class);
    $this->patterns = new ArrayObject();

    $cache = Mockery::mock(CacheService::class);
    $cache->shouldReceive('deletePattern')->andReturnUsing(function (string $pattern): int {
        $this->patterns[] = $pattern;

        return 0;
    });

    App::container()->set(CacheService::class, fn () => $cache);
});

afterEach(function () {
    $original = $this->originalCache;
    App::container()->set(CacheService::class, fn () => $original);
});

test('purging Discover reaches every cached variant of the page', function (string $key) {
    (new PublicCacheInvalidator())->purgeDiscover();

    expect($this->patterns->getArrayCopy())->toHaveCount(1)
        ->and(fnmatch($this->patterns[0], $key, FNM_CASEFOLD))->toBeTrue();
})->with([
    'English' => ['en:GET:/discover'],
    'a tab in Greek' => ['el:GET:/discover?tab=posts'],
    'a search, second page' => ['ar:GET:/discover?page=2&q=garden'],
    'stored headers' => ['en:GET:/discover?tab=posts#headers'],
]);

test('purging Discover leaves blog pages alone', function () {
    (new PublicCacheInvalidator())->purgeDiscover();

    expect(fnmatch($this->patterns[0], 'en:GET:/blog/field-notes', FNM_CASEFOLD))->toBeFalse()
        ->and(fnmatch($this->patterns[0], 'en:GET:/', FNM_CASEFOLD))->toBeFalse();
});
