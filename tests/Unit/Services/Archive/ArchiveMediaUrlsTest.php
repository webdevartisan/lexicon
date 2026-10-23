<?php

declare(strict_types=1);

use App\Services\Archive\ArchiveMediaUrls;

/**
 * The editor stores one uploaded file under several spellings. Export has to see
 * through all of them, and import has to rewrite only exact archive paths.
 */
beforeEach(function () {
    $this->urls = new ArchiveMediaUrls('https://lexicon.test');
});

it('reduces every spelling of an upload to one canonical URL', function (string $value) {
    expect($this->urls->canonical($value))->toBe('/uploads/blogs/0123456789abcdef/2026/10/image-123.jpg');
})->with([
    'library' => '/uploads/blogs/0123456789abcdef/2026/10/image-123.jpg',
    'tinymce relative' => '../../../../uploads/blogs/0123456789abcdef/2026/10/image-123.jpg',
    'site address' => 'https://lexicon.test/uploads/blogs/0123456789abcdef/2026/10/image-123.jpg',
    'locale prefix' => '/el/uploads/blogs/0123456789abcdef/2026/10/image-123.jpg',
    'query string' => '/uploads/blogs/0123456789abcdef/2026/10/image-123.jpg?v=2',
]);

it('treats outside and traversing addresses as not ours', function (string $value) {
    expect($this->urls->canonical($value))->toBeNull();
})->with([
    'other site' => 'https://example.com/uploads/x.jpg',
    'traversal' => '/uploads/../.env',
    'plain relative' => 'media/a.jpg',
]);

it('tells whether an upload sits in a given blog folder', function () {
    $url = '/uploads/blogs/0123456789abcdef/2026/10/image-123.jpg';

    expect($this->urls->isInBlogFolder($url, '0123456789abcdef'))->toBeTrue()
        ->and($this->urls->isInBlogFolder($url, 'fedcba9876543210'))->toBeFalse()
        ->and($this->urls->isInBlogFolder('/uploads/avatars/2026/10/avatar-1.jpg', '0123456789abcdef'))->toBeFalse();
});

it('rewrites src, href and every srcset candidate', function () {
    $html = '<img src="a" srcset="a 1x, b 2x"><a href=\'b\'>x</a><img src="keep">';

    $out = $this->urls->rewrite($html, fn (string $v): ?string => ['a' => 'A', 'b' => 'B'][$v] ?? null);

    expect($out)->toBe('<img src="A" srcset="A 1x, B 2x"><a href=\'B\'>x</a><img src="keep">');
});

it('removes images and links to files that cannot be imported, and counts them', function () {
    $html = '<p><img src="media/x.gif" alt="g"> text <a href="media/x.gif">link</a> <img src="media/ok.jpg"></p>';

    [$out, $removed] = $this->urls->remove($html, ['media/x.gif']);

    expect($out)->toBe('<p> text <a>link</a> <img src="media/ok.jpg"></p>')
        ->and($removed)->toBe(2);
});
