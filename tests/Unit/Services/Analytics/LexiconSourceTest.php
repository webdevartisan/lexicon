<?php

declare(strict_types=1);

use App\Services\Analytics\LexiconSource;

test('a blog source reads back as the same blog', function () {
    expect(LexiconSource::blog(42))->toBe('blog:42')
        ->and(LexiconSource::blogId(LexiconSource::blog(42)))->toBe(42);
});

test('a platform page or anything malformed names no blog', function (string $source) {
    expect(LexiconSource::blogId($source))->toBeNull();
})->with(['discover', 'other', 'blog:', 'blog:12a', 'blog:-3', 'Google']);
